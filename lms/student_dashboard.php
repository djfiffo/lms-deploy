<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}
include 'header.php';

$student_id = $_SESSION['user_id'];
$courses = $pdo->query("SELECT c.*, u.fullname as teacher_name FROM courses c JOIN users u ON c.teacher_id = u.user_id")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <p class="text-muted mb-1">พื้นที่เรียนของคุณ</p>
        <h3 class="mb-0 text-primary fw-bold">รายวิชาทั้งหมด</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="progress.php" class="btn btn-pastel">ความก้าวหน้า</a>
        <a href="notifications.php" class="btn btn-light">แจ้งเตือน</a>
    </div>
</div>
<div class="row">
    <?php foreach($courses as $course): ?>
    <div class="col-md-4 mb-4">
        <div class="card card-custom h-100">
            <div class="card-body">
                <h5 class="card-title"><?php echo $course['title']; ?></h5>
                <p class="text-muted small">รหัสวิชา: <?php echo $course['course_code']; ?></p>
                <p class="card-text"><?php echo $course['description']; ?></p>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <a href="course_agreements.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-pastel w-100">เข้าสู่บทเรียน</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include 'footer.php'; ?>