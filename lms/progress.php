<?php
require_once 'config.php';
require_roles(['student']);
$stmt = $pdo->prepare("SELECT c.course_id, c.course_code, c.title, COUNT(DISTINCT u.unit_id) AS total_units, COUNT(DISTINCT CASE WHEN sp.step_key = 'unit_posttest' AND sp.is_completed = 1 THEN u.unit_id END) AS completed_units FROM course_agreements ca JOIN courses c ON c.course_id = ca.course_id LEFT JOIN units u ON u.course_id = c.course_id LEFT JOIN student_progress sp ON sp.unit_id = u.unit_id AND sp.student_id = ca.student_id WHERE ca.student_id = ? GROUP BY c.course_id, c.course_code, c.title ORDER BY c.title");
$stmt->execute([$_SESSION['user_id']]); $courses = $stmt->fetchAll();
$history = $pdo->prepare("SELECT er.score, er.total_score, er.passed, er.completed_at, e.title, c.title AS course_title FROM exam_results er JOIN exams e ON e.exam_id = er.exam_id JOIN courses c ON c.course_id = e.course_id WHERE er.student_id = ? ORDER BY er.completed_at DESC");
$history->execute([$_SESSION['user_id']]); $results = $history->fetchAll();
include 'header.php';
?>
<h3 class="text-primary">ความก้าวหน้าและประวัติการเรียน</h3>
<div class="row"><?php foreach ($courses as $course): $percent = (int)$course['total_units'] ? round(((int)$course['completed_units'] / (int)$course['total_units']) * 100) : 0; ?>
<div class="col-md-6 mb-3"><div class="card card-custom p-3"><strong><?php echo e($course['course_code'] . ' ' . $course['title']); ?></strong><div class="progress my-2"><div class="progress-bar" style="width: <?php echo $percent; ?>%"><?php echo $percent; ?>%</div></div><a href="course_dashboard.php?course_id=<?php echo (int)$course['course_id']; ?>">เข้าสู่รายวิชา</a></div></div>
<?php endforeach; ?></div>
<h5 class="mt-3">ประวัติการทำแบบทดสอบ</h5><div class="table-responsive"><table class="table"><thead><tr><th>ข้อสอบ</th><th>รายวิชา</th><th>คะแนน</th><th>ผล</th><th>เวลา</th></tr></thead><tbody>
<?php foreach ($results as $result): ?><tr><td><?php echo e($result['title']); ?></td><td><?php echo e($result['course_title']); ?></td><td><?php echo (int)$result['score']; ?>/<?php echo (int)$result['total_score']; ?></td><td><?php echo $result['passed'] ? 'ผ่าน' : 'ไม่ผ่าน'; ?></td><td><?php echo e($result['completed_at']); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php include 'footer.php'; ?>
