<?php
require_once 'config.php';
require_roles(['admin']);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'student';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username) || $fullname === '' || strlen($password) < 8 || !in_array($role, ['admin','teacher','student'], true)) {
        $error = 'กรุณากรอกข้อมูลให้ถูกต้อง (รหัสผ่านอย่างน้อย 8 ตัวอักษร)';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullname, trim($_POST['email'] ?? ''), $role]);
        } catch (PDOException $exception) {
            $error = 'ไม่สามารถสร้างผู้ใช้ได้ อาจมีชื่อผู้ใช้นี้แล้ว';
        }
    }
}
$users = $pdo->query('SELECT user_id, username, fullname, email, role, created_at FROM users ORDER BY user_id DESC')->fetchAll();
include 'header.php';
?>
<h3 class="text-primary">จัดการผู้ใช้</h3>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
<div class="card card-custom p-4 mb-4"><form method="POST" class="row g-2"><?php echo csrf_field(); ?>
    <input class="form-control col" name="username" placeholder="Username" required>
    <input class="form-control col" name="fullname" placeholder="ชื่อ-นามสกุล" required>
    <input class="form-control col" type="email" name="email" placeholder="อีเมล">
    <input class="form-control col" type="password" name="password" minlength="8" placeholder="รหัสผ่าน" required>
    <select class="form-select col" name="role"><option value="student">นักเรียน</option><option value="teacher">ผู้สอน</option><option value="admin">ผู้ดูแล</option></select>
    <button class="btn btn-pastel">เพิ่มผู้ใช้</button>
</form></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Username</th><th>ชื่อ</th><th>บทบาท</th><th>อีเมล</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><?php echo e($user['username']); ?></td><td><?php echo e($user['fullname']); ?></td><td><?php echo e($user['role']); ?></td><td><?php echo e($user['email']); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php include 'footer.php'; ?>
