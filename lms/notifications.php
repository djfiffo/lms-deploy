<?php
require_once 'config.php';
require_login();
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$_SESSION['user_id']]);
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]); $notifications = $stmt->fetchAll();
include 'header.php';
?>
<h3 class="text-primary">การแจ้งเตือน</h3>
<?php if (!$notifications): ?><div class="alert alert-info">ยังไม่มีการแจ้งเตือน</div><?php endif; ?>
<?php foreach ($notifications as $notification): ?><div class="alert alert-light border"><a href="<?php echo e($notification['link'] ?: '#'); ?>"><?php echo e($notification['message']); ?></a><small class="d-block text-muted"><?php echo e($notification['created_at']); ?></small></div><?php endforeach; ?>
<?php include 'footer.php'; ?>
