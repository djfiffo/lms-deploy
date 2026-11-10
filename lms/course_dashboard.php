<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare(
    "SELECT c.*
     FROM courses c
     JOIN course_agreements ca ON ca.course_id = c.course_id AND ca.student_id = ?
     WHERE c.course_id = ?"
);
$stmt->execute([$_SESSION['user_id'], $course_id]);
$course = $stmt->fetch();
if (!$course) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงรายวิชานี้');
}
$pretest = $pdo->prepare("SELECT e.exam_id FROM exams e WHERE e.course_id = ? AND e.exam_type = 'course_pretest' LIMIT 1");
$pretest->execute([$course_id]);
$pretest_id = $pretest->fetchColumn();
if ($pretest_id) {
    $completed = $pdo->prepare("SELECT 1 FROM exam_results WHERE student_id = ? AND exam_id = ? LIMIT 1");
    $completed->execute([$_SESSION['user_id'], $pretest_id]);
    if (!$completed->fetchColumn()) {
        header('Location: course_pretest.php?course_id=' . $course_id);
        exit();
    }
}

$unit_stmt = $pdo->prepare("SELECT * FROM units WHERE course_id = ? ORDER BY unit_number, unit_id");
$unit_stmt->execute([$course_id]);
$units = $unit_stmt->fetchAll();
$review_stmt = $pdo->prepare("SELECT is_completed FROM course_progress WHERE student_id = ? AND course_id = ? AND step_key = 'course_review'");
$review_stmt->execute([$_SESSION['user_id'], $course_id]);
$review_done = (bool)$review_stmt->fetchColumn();
$completion_stmt = $pdo->prepare(
    "SELECT COUNT(*) AS unit_count,
            SUM(CASE WHEN e.exam_id IS NOT NULL AND er.passed = 1 THEN 1 ELSE 0 END) AS passed_count
     FROM units u
     LEFT JOIN exams e ON e.unit_id = u.unit_id AND e.exam_type = 'unit_posttest'
     LEFT JOIN exam_results er ON er.exam_id = e.exam_id AND er.student_id = ? AND er.passed = 1
     WHERE u.course_id = ?"
);
$completion_stmt->execute([$_SESSION['user_id'], $course_id]);
$completion = $completion_stmt->fetch();
$units_done = (int)$completion['unit_count'] > 0 && (int)$completion['passed_count'] >= (int)$completion['unit_count'];
$course_posttest_stmt = $pdo->prepare("SELECT exam_id FROM exams WHERE course_id = ? AND exam_type = 'course_posttest' ORDER BY exam_id DESC LIMIT 1");
$course_posttest_stmt->execute([$course_id]);
$course_posttest_id = $course_posttest_stmt->fetchColumn();

include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-primary mb-1"><?php echo htmlspecialchars($course['title']); ?></h3>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($course['course_code']); ?></p>
    </div>
</div>
<div class="row">
    <?php foreach ($units as $unit): ?>
        <div class="col-md-6 mb-3">
            <div class="card card-custom h-100">
                <div class="card-body">
                    <span class="badge bg-pastel-blue text-dark mb-2">หน่วยที่ <?php echo (int)$unit['unit_number']; ?></span>
                    <h5><?php echo htmlspecialchars($unit['title']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($unit['description'] ?? ''); ?></p>
                    <a class="btn btn-pastel" href="unit_player.php?unit_id=<?php echo (int)$unit['unit_id']; ?>">เข้าสู่หน่วยการเรียน</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="card card-custom p-4 mt-2">
    <h4 class="text-primary">สรุปรายวิชา</h4>
    <p>เมื่อเรียนครบทั้ง 3 หน่วย ให้ทบทวนความรู้รายวิชา ทำแบบทดสอบหลังเรียนรายวิชา และตรวจรับประกาศนียบัตร</p>
    <a class="btn btn-outline-primary me-2 mb-2" href="course_review.php?course_id=<?php echo (int)$course_id; ?>">ทบทวนความรู้ประจำวิชา</a>
    <?php if ($review_done && $units_done && $course_posttest_id): ?>
        <a class="btn btn-pastel me-2 mb-2" href="exam.php?exam_id=<?php echo (int)$course_posttest_id; ?>">สอบหลังเรียนรายวิชา</a>
    <?php else: ?>
        <button class="btn btn-outline-secondary me-2 mb-2" disabled>สอบหลังเรียนรายวิชา (ทำกิจกรรมทบทวนและผ่านทุกหน่วยก่อน)</button>
    <?php endif; ?>
    <a class="btn btn-outline-success mb-2" href="certificate.php?course_id=<?php echo (int)$course_id; ?>">ประกาศนียบัตร</a>
</div>
<?php include 'footer.php'; ?>