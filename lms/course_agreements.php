<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header("Location: login.php"); exit(); }

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$student_id = $_SESSION['user_id'];

if (!$course_id) {
    header("Location: student_dashboard.php");
    exit();
}

$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();

if (!$course) {
    header("Location: student_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("INSERT IGNORE INTO course_agreements (student_id, course_id) VALUES (?, ?)");
    $stmt->execute([$student_id, $course_id]);
    header("Location: course_pretest.php?course_id=" . $course_id);
    exit();
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card card-custom p-4">
            <h3 class="text-center text-primary mb-3">แนะนำบทเรียน: <?php echo htmlspecialchars($course['title']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($course['description'] ?? '')); ?></p>
            <?php if (!empty($course['learning_objectives'])): ?>
                <h5>วัตถุประสงค์การเรียนรู้</h5>
                <div class="mb-3"><?php echo nl2br(htmlspecialchars($course['learning_objectives'])); ?></div>
            <?php endif; ?>
            <?php $intro_video = video_embed_url($course['course_video_url'] ?? ''); ?>
            <?php if ($intro_video): ?>
                <h5>คลิปแนะนำรายวิชา</h5>
                <div class="ratio ratio-16x9 mb-3"><iframe src="<?php echo e($intro_video); ?>" title="คลิปแนะนำรายวิชา" allowfullscreen loading="lazy"></iframe></div>
            <?php endif; ?>
            <div class="border p-3 rounded bg-light mb-4" style="max-height: 200px; overflow-y: scroll;">
                <p>1. ดูคลิปแนะนำและทำแบบทดสอบก่อนเรียนรายวิชา</p>
                <p>2. เรียนทั้ง 3 หน่วยตามลำดับ ตั้งแต่วัตถุประสงค์ แบบทดสอบก่อนเรียน เนื้อหาและกิจกรรม ไปจนถึงแบบทดสอบหลังเรียน</p>
                <p>3. ทบทวนความรู้รายวิชาและทำแบบทดสอบหลังเรียนรายวิชาให้ได้อย่างน้อย 70% เพื่อรับประกาศนียบัตร</p>
            </div>
            <form method="POST" class="text-center">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-pastel-yellow btn-lg px-5">ยอมรับเงื่อนไขและเข้าสู่ข้อสอบก่อนเรียน</button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>