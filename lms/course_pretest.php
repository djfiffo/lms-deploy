<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
if (!$course_id) {
    header('Location: student_dashboard.php');
    exit();
}

$stmt = $pdo->prepare(
    "SELECT c.*, ca.agreed_at
     FROM courses c
     JOIN course_agreements ca ON ca.course_id = c.course_id AND ca.student_id = ?
     WHERE c.course_id = ?"
);
$stmt->execute([$_SESSION['user_id'], $course_id]);
$course = $stmt->fetch();
if (!$course) {
    http_response_code(403);
    exit('กรุณายอมรับข้อตกลงก่อนเข้าเรียน');
}

$exam_stmt = $pdo->prepare("SELECT * FROM exams WHERE course_id = ? AND exam_type = 'course_pretest' LIMIT 1");
$exam_stmt->execute([$course_id]);
$exam = $exam_stmt->fetch();
$result = null;
if ($exam) {
    $result_stmt = $pdo->prepare(
        "SELECT score, total_score, passed, completed_at
         FROM exam_results
         WHERE student_id = ? AND exam_id = ?
         ORDER BY completed_at DESC, result_id DESC
         LIMIT 1"
    );
    $result_stmt->execute([$_SESSION['user_id'], $exam['exam_id']]);
    $result = $result_stmt->fetch();
}

include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card card-custom p-4">
            <h3 class="text-primary mb-3">แบบทดสอบก่อนเรียน: <?php echo htmlspecialchars($course['title']); ?></h3>
            <p>ทำแบบทดสอบก่อนเรียนเพื่อประเมินความรู้เบื้องต้นก่อนเริ่มรายวิชา</p>
            <?php if ($exam && $result): ?>
                <div class="alert <?php echo $result['passed'] ? 'alert-success' : 'alert-warning'; ?>">
                    ทำแบบทดสอบแล้ว ได้คะแนน <?php echo (int)$result['score']; ?>/<?php echo (int)$result['total_score']; ?>
                    (แบบทดสอบก่อนเรียนทำได้ครั้งเดียว)
                </div>
                <a class="btn btn-pastel" href="course_dashboard.php?course_id=<?php echo (int)$course_id; ?>">เข้าสู่รายวิชา</a>
            <?php elseif ($exam): ?>
                <a class="btn btn-pastel" href="exam.php?exam_id=<?php echo (int)$exam['exam_id']; ?>">เริ่มทำแบบทดสอบ</a>
            <?php else: ?>
                <div class="alert alert-info">รายวิชานี้ยังไม่มีแบบทดสอบก่อนเรียน</div>
                <a class="btn btn-pastel" href="course_dashboard.php?course_id=<?php echo (int)$course_id; ?>">เข้าสู่รายวิชา</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>