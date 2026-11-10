<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$exam_id = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);
if (!$exam_id) {
    header('Location: student_dashboard.php');
    exit();
}

$exam_stmt = $pdo->prepare(
    "SELECT e.*, c.title AS course_title, c.course_id, u.title AS unit_title, u.unit_id
     FROM exams e
     JOIN courses c ON c.course_id = e.course_id
     LEFT JOIN units u ON u.unit_id = e.unit_id
     JOIN course_agreements ca ON ca.course_id = e.course_id AND ca.student_id = ?
     WHERE e.exam_id = ?"
);
$exam_stmt->execute([$_SESSION['user_id'], $exam_id]);
$exam = $exam_stmt->fetch();
if (!$exam) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงแบบทดสอบนี้');
}
if ($exam['exam_type'] === 'course_posttest') {
    $review_stmt = $pdo->prepare("SELECT 1 FROM course_progress WHERE student_id = ? AND course_id = ? AND step_key = 'course_review' AND is_completed = 1");
    $review_stmt->execute([$_SESSION['user_id'], $exam['course_id']]);
    $unit_stmt = $pdo->prepare(
        "SELECT COUNT(*) AS unit_count,
                SUM(CASE WHEN e.exam_id IS NOT NULL AND er.passed = 1 THEN 1 ELSE 0 END) AS passed_count
         FROM units u
         LEFT JOIN exams e ON e.unit_id = u.unit_id AND e.exam_type = 'unit_posttest'
         LEFT JOIN exam_results er ON er.exam_id = e.exam_id AND er.student_id = ? AND er.passed = 1
         WHERE u.course_id = ?"
    );
    $unit_stmt->execute([$_SESSION['user_id'], $exam['course_id']]);
    $unit_status = $unit_stmt->fetch();
    if (!$review_stmt->fetchColumn() || (int)$unit_status['unit_count'] === 0 || (int)$unit_status['passed_count'] < (int)$unit_status['unit_count']) {
        http_response_code(403);
        exit('กรุณาทบทวนรายวิชาและผ่านแบบทดสอบหลังเรียนทุกหน่วยก่อน');
    }
}
if ($exam['unit_id'] && $exam['exam_type'] === 'unit_pretest') {
    $objective = $pdo->prepare("SELECT 1 FROM student_progress WHERE student_id = ? AND unit_id = ? AND step_key = 'objectives' AND is_completed = 1");
    $objective->execute([$_SESSION['user_id'], $exam['unit_id']]);
    if (!$objective->fetchColumn()) {
        http_response_code(403);
        exit('กรุณาอ่านวัตถุประสงค์การเรียนรู้ก่อนทำแบบทดสอบ');
    }
}
if ($exam['unit_id'] && $exam['exam_type'] === 'unit_posttest') {
    $review = $pdo->prepare("SELECT 1 FROM student_progress WHERE student_id = ? AND unit_id = ? AND step_key = 'unit_review' AND is_completed = 1");
    $review->execute([$_SESSION['user_id'], $exam['unit_id']]);
    if (!$review->fetchColumn()) {
        http_response_code(403);
        exit('กรุณาเรียนเนื้อหาและทำกิจกรรมทบทวนของหน่วยนี้ก่อน');
    }
}
if ($exam['exam_type'] !== 'course_pretest' && !$exam['unit_id']) {
    $coursePretest = $pdo->prepare(
        "SELECT 1 FROM exam_results er JOIN exams e ON e.exam_id = er.exam_id
         WHERE er.student_id = ? AND e.course_id = ? AND e.exam_type = 'course_pretest' LIMIT 1"
    );
    $coursePretest->execute([$_SESSION['user_id'], $exam['course_id']]);
    if (!$coursePretest->fetchColumn()) {
        header('Location: course_pretest.php?course_id=' . (int)$exam['course_id']);
        exit();
    }
}
log_activity($pdo, $_SESSION['user_id'], 'view_exam', 'exam.php?exam_id=' . $exam_id);
log_learning_event($pdo, $_SESSION['user_id'], 'exam_viewed', [], (int)$exam['course_id'], $exam['unit_id'] ? (int)$exam['unit_id'] : null);

$question_stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY question_id");
$question_stmt->execute([$exam_id]);
$questions = $question_stmt->fetchAll();
$result = null;
$existing_stmt = $pdo->prepare(
    "SELECT score, total_score, passed, completed_at
     FROM exam_results
     WHERE student_id = ? AND exam_id = ?
     ORDER BY completed_at DESC, result_id DESC
     LIMIT 1"
);
$existing_stmt->execute([$_SESSION['user_id'], $exam_id]);
$existing = $existing_stmt->fetch();
$is_course_pretest = $exam['exam_type'] === 'course_pretest';
$is_completed = $existing && ($is_course_pretest || (bool)$existing['passed']);
$allow_retry = $existing && !$is_completed;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($is_completed) {
        $result = $existing;
    }
    if ($is_completed) {
        // A course pretest is diagnostic and one-time; passed unit exams are final.
    } elseif (count($questions) === 0) {
        http_response_code(422);
        exit('แบบทดสอบยังไม่มีคำถาม');
    } else {
        $score = 0;
        foreach ($questions as $question) {
            $answer = $_POST['answer'][$question['question_id']] ?? '';
            if ($answer === $question['correct_option']) {
                $score++;
            }
        }

        $total_score = count($questions);
        $pass_percentage = (float)($exam['pass_percentage'] ?? 70);
        $passed = $total_score > 0 && (($score / $total_score) * 100) >= $pass_percentage;
        $insert = $pdo->prepare("INSERT INTO exam_results (student_id, exam_id, score, total_score, passed) VALUES (?, ?, ?, ?, ?)");
        $insert->execute([$_SESSION['user_id'], $exam_id, $score, $total_score, $passed ? 1 : 0]);
        log_activity($pdo, $_SESSION['user_id'], 'submit_exam', 'exam.php?exam_id=' . $exam_id);
        log_learning_event($pdo, $_SESSION['user_id'], 'exam_submitted', [
            'score_percent' => $total_score > 0 ? round(($score / $total_score) * 100, 2) : 0,
            'score' => $score,
            'total_score' => $total_score,
            'passed' => $passed,
        ], (int)$exam['course_id'], $exam['unit_id'] ? (int)$exam['unit_id'] : null);
        $result = ['score' => $score, 'total_score' => $total_score, 'passed' => $passed];

        if ($passed && $exam['unit_id'] && in_array($exam['exam_type'], ['unit_pretest', 'unit_posttest'], true)) {
            $step_key = $exam['exam_type'];
            $progress = $pdo->prepare(
                "INSERT INTO student_progress (student_id, unit_id, step_key, is_completed)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE is_completed = 1"
            );
            $progress->execute([$_SESSION['user_id'], $exam['unit_id'], $step_key]);
        }

        // Redirect after submission so refresh cannot repeat the POST.
        header('Location: exam.php?exam_id=' . $exam_id . '&submitted=1');
        exit();
    }
}

if (!$result && $existing) {
    $result = $existing;
}

include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card card-custom p-4">
            <h3 class="text-primary"><?php echo htmlspecialchars($exam['title']); ?></h3>
            <p class="text-muted">รายวิชา: <?php echo htmlspecialchars($exam['course_title']); ?></p>
            <?php if ($result): ?>
                <div class="alert <?php echo $result['passed'] ? 'alert-success' : 'alert-warning'; ?>">
                    ได้คะแนน <?php echo (int)$result['score']; ?>/<?php echo (int)$result['total_score']; ?>
                    <?php echo $result['passed'] ? 'ผ่านเกณฑ์' : 'ยังไม่ผ่านเกณฑ์'; ?>
                    <?php if ($is_course_pretest): ?> (แบบทดสอบก่อนเรียนทำได้ครั้งเดียว)<?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($result && !$allow_retry): ?>
                <?php if ($exam['unit_id']): ?>
                    <a class="btn btn-pastel" href="unit_player.php?unit_id=<?php echo (int)$exam['unit_id']; ?>">กลับไปยังหน่วยการเรียน</a>
                <?php else: ?>
                    <a class="btn btn-pastel" href="course_dashboard.php?course_id=<?php echo (int)$exam['course_id']; ?>">เข้าสู่รายวิชา</a>
                <?php endif; ?>
            <?php elseif (count($questions) === 0): ?>
                <div class="alert alert-info">แบบทดสอบนี้ยังไม่มีคำถาม</div>
            <?php else: ?>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                    <?php foreach ($questions as $index => $question): ?>
                        <fieldset class="mb-4">
                            <legend class="fs-5"><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></legend>
                            <?php foreach (['A', 'B', 'C', 'D'] as $option): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" required name="answer[<?php echo (int)$question['question_id']; ?>]" value="<?php echo $option; ?>" id="q<?php echo (int)$question['question_id'] . $option; ?>">
                                    <label class="form-check-label" for="q<?php echo (int)$question['question_id'] . $option; ?>"><?php echo $option . '. ' . htmlspecialchars($question['option_' . strtolower($option)]); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <button class="btn btn-pastel" type="submit">ส่งคำตอบ</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>