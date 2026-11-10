<?php
require_once 'config.php';
require_roles(['admin', 'teacher']);

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
if ($course_id) {
    $course_stmt = $pdo->prepare('SELECT teacher_id FROM courses WHERE course_id = ?');
    $course_stmt->execute([$course_id]);
    $course_teacher = $course_stmt->fetchColumn();
    if (!$course_teacher || ($_SESSION['role'] === 'teacher' && (int)$course_teacher !== (int)$_SESSION['user_id'])) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์ดู Risk ของรายวิชานี้');
    }
}
$course_condition = $course_id ? ' AND ca.course_id = ?' : '';
$event_condition = $course_id ? ' AND le.course_id = ?' : '';
$query = $pdo->prepare(
    "SELECT u.user_id, u.fullname, MAX(le.occurred_at) AS last_event,
        COUNT(le.event_id) AS event_count,
        COALESCE(MAX(CASE WHEN le.event_type = 'progress' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(le.payload, '$.progress_ratio')) AS DECIMAL(5,3)) END), 0) AS progress_ratio,
        COALESCE(AVG(CASE WHEN le.event_type = 'exam_submitted' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(le.payload, '$.score_percent')) AS DECIMAL(5,2)) END), 0) AS average_score,
        COALESCE(SUM(CASE WHEN le.event_type = 'exam_submitted' THEN 1 ELSE 0 END), 0) AS attempt_count,
        COALESCE(SUM(CASE WHEN le.event_type = 'content_viewed' THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(le.payload, '$.minutes')) AS UNSIGNED) ELSE 0 END), 0) AS content_minutes,
        COALESCE(SUM(CASE WHEN le.event_type = 'forum_question' THEN 1 ELSE 0 END), 0) AS forum_questions
     FROM users u
     JOIN course_agreements ca ON ca.student_id = u.user_id" . $course_condition . "
     LEFT JOIN learning_events le ON le.user_id = u.user_id" . $event_condition . "
     WHERE u.role = 'student'
     GROUP BY u.user_id, u.fullname
     ORDER BY u.fullname"
);
$query->execute($course_id ? [$course_id, $course_id] : []);
$students = $query->fetchAll();
$client = service_client();
$prediction_stmt = $pdo->prepare(
    'INSERT INTO risk_predictions (user_id, course_id, risk_score, risk_level, model_version, reasons) VALUES (?, ?, ?, ?, ?, ?)'
);
$recent_prediction_stmt = $pdo->prepare(
    'SELECT prediction_id FROM risk_predictions
     WHERE user_id = ? AND course_id <=> ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY created_at DESC LIMIT 1'
);
foreach ($students as &$student) {
    $days_since_login = $student['last_event']
        ? max(0, (int)floor((time() - strtotime($student['last_event'])) / 86400))
        : 999;
    $features = [
        'days_since_login' => $days_since_login,
        'progress_ratio' => (float)$student['progress_ratio'],
        'average_score' => (float)$student['average_score'],
        'attempt_count' => (int)$student['attempt_count'],
        'content_minutes' => (int)$student['content_minutes'],
        'forum_questions' => (int)$student['forum_questions'],
    ];
    try {
        $response = $client->request('predict/risk', $features);
        $prediction = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        if (!isset($prediction['risk_score'], $prediction['risk_level'])) {
            throw new RuntimeException('ML service returned an incomplete prediction.');
        }
        $model_version = $response['model_version'] ?? 'external';
    } catch (RuntimeException $error) {
        $prediction = ['risk_score' => null, 'risk_level' => 'unavailable'];
        $model_version = 'unavailable';
    }
    $student['prediction'] = $prediction;
    $student['features'] = $features;
    $reasons = [];
    if ($days_since_login >= 7) $reasons[] = 'ไม่มี event ล่าสุดเกิน 7 วัน';
    if ($features['progress_ratio'] < 0.4) $reasons[] = 'ความก้าวหน้าต่ำกว่า 40%';
    if ($features['average_score'] < 60) $reasons[] = 'คะแนนเฉลี่ยต่ำกว่า 60%';
    $reasons_json = json_encode($reasons, JSON_UNESCAPED_UNICODE);
    $recent_prediction_stmt->execute([$student['user_id'], $course_id]);
    if (!$recent_prediction_stmt->fetchColumn()) {
        $prediction_stmt->execute([
            $student['user_id'], $course_id, $prediction['risk_score'] === null ? 0 : $prediction['risk_score'],
            $prediction['risk_level'], $model_version, $reasons_json
        ]);
    }
    if ($prediction['risk_level'] === 'high') {
        $student_link = $course_id
            ? 'course_dashboard.php?course_id=' . (int)$course_id
            : 'student_dashboard.php';
        $notice = $pdo->prepare(
            "INSERT INTO notifications (user_id, message, link)
             SELECT ?, ?, ?
             WHERE NOT EXISTS (
                 SELECT 1 FROM notifications
                 WHERE user_id = ? AND link = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
             )"
        );
        $notice->execute([
            $student['user_id'],
            'ระบบพบสัญญาณความเสี่ยงสูง กรุณาติดตามความก้าวหน้าและติดต่อผู้เรียน',
            $student_link,
            $student['user_id'],
            $student_link,
        ]);
    }
}
unset($student);
$risk_counts = ['high' => 0, 'medium' => 0, 'low' => 0, 'unavailable' => 0];
foreach ($students as $student) {
    $level = $student['prediction']['risk_level'];
    $risk_counts[$level] = ($risk_counts[$level] ?? 0) + 1;
}
$trend_stmt = $pdo->prepare(
    "SELECT DATE(created_at) AS risk_date, ROUND(AVG(risk_score) * 100, 1) AS average_risk
     FROM risk_predictions
     WHERE course_id <=> ?
     GROUP BY DATE(created_at)
     ORDER BY risk_date DESC
     LIMIT 14"
);
$trend_stmt->execute([$course_id]);
$trend = array_reverse($trend_stmt->fetchAll());
include 'header.php';
?>
<h3 class="text-primary">ML Risk Dashboard</h3>
<p class="text-muted">ผลคำนวณจาก learning events ในฐานข้อมูลจริง</p>
<div class="row g-3 mb-4">
    <div class="col-md-8"><div class="card card-custom p-3"><h5>แนวโน้ม Risk เฉลี่ย</h5><canvas id="riskTrendChart" height="100"></canvas></div></div>
    <div class="col-md-4"><div class="card card-custom p-3"><h5>สัดส่วนผู้เรียน</h5><canvas id="riskLevelChart" height="180"></canvas></div></div>
</div>
<div class="table-responsive"><table class="table table-bordered align-middle">
<thead><tr><th>ผู้เรียน</th><th>Risk</th><th>Progress</th><th>คะแนนเฉลี่ย</th><th>เหตุผล</th></tr></thead><tbody>
<?php foreach ($students as $student): ?>
<tr>
<td><?php echo e($student['fullname']); ?></td>
<td><span class="badge <?php echo $student['prediction']['risk_level'] === 'high' ? 'bg-danger' : ($student['prediction']['risk_level'] === 'medium' ? 'bg-warning text-dark' : 'bg-success'); ?>"><?php echo e($student['prediction']['risk_level']); ?> <?php echo $student['prediction']['risk_score'] === null ? '' : '(' . round($student['prediction']['risk_score'] * 100, 1) . '%)'; ?></span></td>
<td><?php echo round($student['features']['progress_ratio'] * 100, 1); ?>%</td>
<td><?php echo round($student['features']['average_score'], 1); ?>%</td>
<td><?php echo e(implode(', ', $reasons = array_filter([$student['features']['days_since_login'] >= 7 ? 'ไม่เข้าเรียนล่าสุด' : '', $student['features']['progress_ratio'] < 0.4 ? 'progress ต่ำ' : '', $student['features']['average_score'] < 60 ? 'คะแนนต่ำ' : ''])) ?: 'ไม่พบเหตุผลเด่นชัด'); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const trendLabels = <?php echo json_encode(array_column($trend, 'risk_date'), JSON_UNESCAPED_UNICODE); ?>;
const trendValues = <?php echo json_encode(array_map('floatval', array_column($trend, 'average_risk'))); ?>;
new Chart(document.getElementById('riskTrendChart'), {
    type: 'line',
    data: { labels: trendLabels, datasets: [{ label: 'Risk เฉลี่ย (%)', data: trendValues, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.12)', fill: true, tension: .3 }] },
    options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } }
});
new Chart(document.getElementById('riskLevelChart'), {
    type: 'doughnut',
    data: { labels: ['High', 'Medium', 'Low', 'Unavailable'], datasets: [{ data: [<?php echo (int)$risk_counts['high']; ?>, <?php echo (int)$risk_counts['medium']; ?>, <?php echo (int)$risk_counts['low']; ?>, <?php echo (int)$risk_counts['unavailable']; ?>], backgroundColor: ['#dc3545', '#ffc107', '#198754', '#6c757d'] }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
<?php include 'footer.php'; ?>
