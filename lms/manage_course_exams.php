<?php
require_once 'config.php';
require_roles(['admin', 'teacher']);
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("SELECT c.* FROM courses c WHERE c.course_id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course || ($_SESSION['role'] === 'teacher' && (int)$course['teacher_id'] !== (int)$_SESSION['user_id'])) {
    http_response_code(403); exit('ไม่มีสิทธิ์จัดการรายวิชานี้');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    if ($title === '') { http_response_code(422); exit('กรุณาระบุชื่อข้อสอบ'); }
    $exam_type = $_POST['exam_type'] ?? '';
    if (!in_array($exam_type, ['course_pretest', 'course_posttest'], true)) { http_response_code(422); exit('ประเภทข้อสอบไม่ถูกต้อง'); }
    $stmt = $pdo->prepare("INSERT INTO exams (course_id, unit_id, exam_type, title, pass_percentage) VALUES (?, NULL, ?, ?, ?)");
    $stmt->execute([$course_id, $exam_type, $title, max(1, min(100, (float)($_POST['pass_percentage'] ?? 70)))]);
    header('Location: manage_course_exams.php?course_id=' . $course_id); exit();
}
$stmt = $pdo->prepare("SELECT * FROM exams WHERE course_id = ? AND exam_type IN ('course_pretest', 'course_posttest') ORDER BY exam_type, exam_id DESC");
$stmt->execute([$course_id]); $exams = $stmt->fetchAll();
include 'header.php';
?>
<h3 class="text-primary">แบบทดสอบก่อนเรียน: <?php echo e($course['title']); ?></h3>
<div class="card card-custom p-4 mb-4"><form method="POST"><?php echo csrf_field(); ?>
    <select class="form-select mb-2" name="exam_type"><option value="course_pretest">สอบก่อนเรียนรายวิชา</option><option value="course_posttest">สอบหลังเรียนรายวิชา</option></select>
    <input class="form-control mb-2" name="title" placeholder="ชื่อแบบทดสอบ" required>
    <input class="form-control mb-2" type="number" name="pass_percentage" value="70" min="1" max="100" required>
    <button class="btn btn-pastel">สร้างแบบทดสอบ</button>
</form></div>
<div class="list-group"><?php foreach ($exams as $exam): ?>
    <a class="list-group-item list-group-item-action" href="manage_questions.php?exam_id=<?php echo (int)$exam['exam_id']; ?>"><?php echo e($exam['title']); ?> <span class="badge bg-secondary"><?php echo e($exam['exam_type']); ?></span></a>
<?php endforeach; ?></div>
<?php include 'footer.php'; ?>
