<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_matches($user['password'], $password)) {
        maybe_upgrade_password($pdo, $user, $password);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        log_activity($pdo, $user['user_id'], 'login', 'login.php');
        log_learning_event($pdo, $user['user_id'], 'login');

        if ($user['role'] === 'student') {
            header("Location: student_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card card-custom p-4 mt-4">
            <h3 class="text-center mb-4 text-primary">เข้าสู่ระบบการเรียน</h3>
            <?php if($error): ?>
                <div class="alert alert-danger py-2"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">รหัสผ่าน (Password)</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-pastel w-100 py-2">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>