<?php
require_once 'config.php';
$code = trim($_GET['code'] ?? '');
$stmt = $pdo->prepare("SELECT ce.cert_code, ce.issued_at, u.fullname, c.course_code, c.title, ce.revoked_at FROM certificates ce JOIN users u ON u.user_id = ce.student_id JOIN courses c ON c.course_id = ce.course_id WHERE ce.cert_code = ?");
$stmt->execute([$code]);
$certificate = $stmt->fetch();
include 'header.php';
?>
<div class="card card-custom p-5 text-center">
<h3 class="text-primary">ตรวจสอบใบรับรอง</h3>
<?php if ($certificate && !$certificate['revoked_at']): ?>
<div class="alert alert-success">ใบรับรองถูกต้อง</div>
<p>ผู้เรียน: <strong><?php echo e($certificate['fullname']); ?></strong></p>
<p>รายวิชา: <?php echo e($certificate['course_code'] . ' - ' . $certificate['title']); ?></p>
<p>รหัส: <?php echo e($certificate['cert_code']); ?> | ออกเมื่อ: <?php echo e($certificate['issued_at']); ?></p>
<?php else: ?><div class="alert alert-danger">ไม่พบใบรับรองหรือใบรับรองถูกยกเลิก</div><?php endif; ?>
</div>
<?php include 'footer.php'; ?>
