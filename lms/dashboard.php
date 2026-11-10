<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'student') {
    header("Location: login.php");
    exit();
}
include 'header.php';

// ดึงวิชาทั้งหมด
$stmt = $pdo->prepare("SELECT c.*, u.fullname as teacher_name FROM courses c JOIN users u ON c.teacher_id = u.user_id");
$stmt->execute();
$courses = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <p class="text-muted mb-1">พื้นที่ทำงานของ<?php echo $_SESSION['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้สอน'; ?></p>
        <h2 class="fw-bold mb-0">จัดการรายวิชา</h2>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="manage_course.php" class="btn btn-pastel-yellow">+ สร้างรายวิชาใหม่</a>
        <a href="student_reports.php" class="btn btn-light">รายงานผู้เรียน</a>
        <a href="ml_risk.php" class="btn btn-light">ดู Risk</a>
    </div>
</div>

<div class="row">
    <?php foreach($courses as $course): ?>
    <div class="col-md-4 mb-4">
        <div class="card card-custom h-100">
            <div class="card-body">
                <span class="badge bg-pastel-blue text-dark mb-2"><?php echo $course['course_code']; ?></span>
                <h5 class="card-title"><?php echo $course['title']; ?></h5>
                <p class="card-text text-muted small"><?php echo mb_strimwidth($course['description'], 0, 90, "..."); ?></p>
                <p class="card-text"><small class="text-muted">ผู้สอน: <?php echo $course['teacher_name']; ?></small></p>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <a href="manage_course.php?course_id=<?php echo (int)$course['course_id']; ?>" class="btn btn-sm btn-outline-primary">แก้ไขรายวิชา</a>
                <a href="manage_units.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-pastel">จัดการเนื้อหา/ข้อสอบ</a>
                <a href="manage_course_exams.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-outline-primary">ข้อสอบก่อนเรียน</a>
                <a href="student_reports.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-outline-secondary">ดูความก้าวหน้า</a>
                <a href="ml_risk.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-outline-danger">ML Risk</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include 'footer.php'; ?>