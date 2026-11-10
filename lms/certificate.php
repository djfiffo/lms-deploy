<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$requested_student = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$student_id = $_SESSION['role'] === 'student' ? $_SESSION['user_id'] : ($requested_student ?: $_SESSION['user_id']);
$owner = $pdo->prepare("SELECT teacher_id FROM courses WHERE course_id = ?");
$owner->execute([$course_id]);
$teacher_id = $owner->fetchColumn();
if ($_SESSION['role'] === 'teacher' && (int)$teacher_id !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์ตรวจสอบใบรับรองนี้');
}
$stmt = $pdo->prepare("SELECT c.*, u.fullname FROM courses c JOIN users u ON u.user_id = ? WHERE c.course_id = ?");
$stmt->execute([$student_id, $course_id]);
$course = $stmt->fetch();
if (!$course) { http_response_code(404); exit('ไม่พบข้อมูลรายวิชา'); }
$agreement = $pdo->prepare("SELECT 1 FROM course_agreements WHERE student_id = ? AND course_id = ?");
$agreement->execute([$student_id, $course_id]);
if (!$agreement->fetchColumn()) { http_response_code(403); exit('ผู้เรียนยังไม่ได้ยอมรับข้อตกลงรายวิชา'); }
$units = $pdo->prepare("SELECT unit_id FROM units WHERE course_id = ?");
$units->execute([$course_id]);
$unit_ids = $units->fetchAll(PDO::FETCH_COLUMN);
$completed = count($unit_ids) > 0;
if ($completed) {
    $marks = implode(',', array_fill(0, count($unit_ids), '?'));
    $progress = $pdo->prepare("SELECT COUNT(DISTINCT unit_id) FROM student_progress WHERE student_id = ? AND unit_id IN ($marks) AND step_key = 'unit_posttest' AND is_completed = 1");
    $progress->execute(array_merge([$student_id], $unit_ids));
    $completed = (int)$progress->fetchColumn() === count($unit_ids);
}
$course_final = $pdo->prepare("SELECT exam_id FROM exams WHERE course_id = ? AND exam_type = 'course_posttest' ORDER BY exam_id DESC LIMIT 1");
$course_final->execute([$course_id]);
$course_final_id = $course_final->fetchColumn();
if ($course_final_id) {
    $course_review = $pdo->prepare("SELECT 1 FROM course_progress WHERE student_id = ? AND course_id = ? AND step_key = 'course_review' AND is_completed = 1");
    $course_review->execute([$student_id, $course_id]);
    $course_final_result = $pdo->prepare("SELECT 1 FROM exam_results WHERE student_id = ? AND exam_id = ? AND passed = 1 LIMIT 1");
    $course_final_result->execute([$student_id, $course_final_id]);
    $completed = $completed && (bool)$course_review->fetchColumn() && (bool)$course_final_result->fetchColumn();
}
$code = null;
if ($completed) {
    $find = $pdo->prepare("SELECT cert_code FROM certificates WHERE student_id = ? AND course_id = ?");
    $find->execute([$student_id, $course_id]);
    $code = $find->fetchColumn();
    if (!$code) { $code = 'LMS-' . strtoupper(bin2hex(random_bytes(5))); $insert = $pdo->prepare("INSERT INTO certificates (student_id, course_id, cert_code) VALUES (?, ?, ?)"); $insert->execute([$student_id, $course_id, $code]); }
}
include 'header.php';
?>
<div class="card card-custom p-5 text-center"><h2 class="text-primary">ใบรับรองการเรียนจบ</h2><p class="lead"><?php echo htmlspecialchars($course['fullname']); ?></p><p>รายวิชา <?php echo htmlspecialchars($course['title']); ?></p>
<?php if ($code): ?><div class="alert alert-success">เรียนจบตามเงื่อนไขแล้ว<br><strong><?php echo htmlspecialchars($code); ?></strong><br><a href="verify_certificate.php?code=<?php echo urlencode($code); ?>">ตรวจสอบใบรับรอง</a> <button class="btn btn-sm btn-outline-primary" onclick="window.print()">พิมพ์ / บันทึก PDF</button></div><?php else: ?><div class="alert alert-warning">ยังเรียนไม่ครบทุกหน่วยหรือยังไม่ผ่านแบบทดสอบหลังเรียน</div><?php endif; ?></div>
<?php include 'footer.php'; ?>