<?php
require_once 'config.php';
require_login();
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$course = $pdo->prepare('SELECT * FROM courses WHERE course_id = ?'); $course->execute([$course_id]); $course = $course->fetch();
if (!$course) { http_response_code(404); exit('ไม่พบรายวิชา'); }
if ($_SESSION['role'] === 'student') {
    $agreement = $pdo->prepare('SELECT 1 FROM course_agreements WHERE student_id = ? AND course_id = ?');
    $agreement->execute([$_SESSION['user_id'], $course_id]);
    if (!$agreement->fetchColumn()) { http_response_code(403); exit('กรุณายอมรับข้อตกลงก่อนเข้ากระดานสนทนา'); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $body = trim($_POST['body'] ?? '');
    if ($body === '') { http_response_code(422); exit('กรุณาระบุข้อความ'); }
    $pdo->prepare('INSERT INTO forum_posts (course_id, user_id, body) VALUES (?, ?, ?)')->execute([$course_id, $_SESSION['user_id'], $body]);
}
$stmt = $pdo->prepare('SELECT f.*, u.fullname FROM forum_posts f JOIN users u ON u.user_id = f.user_id WHERE f.course_id = ? ORDER BY f.created_at DESC');
$stmt->execute([$course_id]); $posts = $stmt->fetchAll();
include 'header.php';
?>
<h3 class="text-primary">กระดานสนทนา: <?php echo e($course['title']); ?></h3>
<form method="POST" class="mb-4"><?php echo csrf_field(); ?><textarea name="body" class="form-control mb-2" required></textarea><button class="btn btn-pastel">โพสต์</button></form>
<?php foreach ($posts as $post): ?><div class="card card-custom p-3 mb-2"><strong><?php echo e($post['fullname']); ?></strong><small><?php echo e($post['created_at']); ?></small><p class="mb-0"><?php echo nl2br(e($post['body'])); ?></p></div><?php endforeach; ?>
<?php include 'footer.php'; ?>
