<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบเรียนออนไลน์ - Hangyodon University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --brand-blue: #aecdfe; --brand-blue-dark: #4d8fe8; --brand-yellow: #fff2b2; --brand-ink: #29415f; }
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f8fb; color: var(--brand-ink); }
        .bg-pastel-blue { background-color: var(--brand-blue) !important; }
        .bg-pastel-yellow { background-color: var(--brand-yellow) !important; }
        .btn-pastel { background-color: var(--brand-blue-dark); color: #fff; border: none; }
        .btn-pastel:hover { background-color: #3478d0; color: #fff; }
        .btn-pastel-yellow { background-color: #ffe57f; color: #444; border: none; }
        .btn-pastel-yellow:hover { background-color: #ffd740; color: #222; }
        .site-header { height: 68px; padding-top: 0; padding-bottom: 0; }
        .site-header > .container { min-height: 68px; }
        .navbar-brand { font-weight: 600; color: #2c3e50 !important; display: inline-flex; align-items: center; gap: .5rem; }
        .brand-logo { width: 42px; height: 42px; object-fit: contain; }
        .card-custom { border-radius: 12px; border: 1px solid rgba(77,143,232,.12); box-shadow: 0 4px 12px rgba(41,65,95,.06); }
        .icon-nav { display: flex; align-items: center; gap: .2rem; }
        .icon-nav .nav-link,
        .icon-nav .icon-button { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; padding: 0; border-radius: 9px; color: var(--brand-ink); text-decoration: none; }
        .icon-nav .nav-link:hover,
        .icon-nav .icon-button:hover { background: rgba(255,255,255,.55); color: #173452; }
        .icon-nav i { font-size: 1.1rem; line-height: 1; }
        .icon-nav .notification-dot { position: absolute; top: 4px; right: 4px; width: 7px; height: 7px; border: 1px solid var(--brand-blue); border-radius: 50%; background: #dc3545; }
        .user-greeting { display: inline-flex; align-items: center; gap: .35rem; margin-left: .35rem; color: var(--brand-ink); font-size: .9rem; font-weight: 600; }
        .user-greeting i { font-size: 1.15rem; }
        @media (max-width: 991.98px) {
            .icon-nav { padding: .75rem 0; justify-content: flex-start; }
            .user-greeting { margin: 0 0 .5rem; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-pastel-blue shadow-sm site-header">
  <div class="container">
    <a class="navbar-brand fs-4" href="index.php">
      <img src="assets/Hangyodonn.png" alt="Hangyodon University Logo" class="brand-logo">
      <span>Hangyodon University</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <?php if(isset($_SESSION['user_id'])): ?>
            <li class="nav-item icon-nav">
            <?php if ($_SESSION['role'] === 'student'): ?>
                <a class="nav-link" href="student_dashboard.php" title="รายวิชา" aria-label="รายวิชา"><i class="bi bi-grid-3x3-gap-fill"></i></a>
                <a class="nav-link" href="progress.php" title="ความก้าวหน้า" aria-label="ความก้าวหน้า"><i class="bi bi-bar-chart-line-fill"></i></a>
            <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                <a class="nav-link" href="dashboard.php" title="รายวิชา" aria-label="รายวิชา"><i class="bi bi-grid-3x3-gap-fill"></i></a>
                <a class="nav-link" href="student_reports.php" title="รายงานผู้เรียน" aria-label="รายงานผู้เรียน"><i class="bi bi-bar-chart-line-fill"></i></a>
                <a class="nav-link" href="ml_risk.php" title="Risk Dashboard" aria-label="Risk Dashboard"><i class="bi bi-shield-exclamation"></i></a>
            <?php else: ?>
                <a class="nav-link" href="dashboard.php" title="จัดการรายวิชา" aria-label="จัดการรายวิชา"><i class="bi bi-grid-3x3-gap-fill"></i></a>
                <a class="nav-link" href="user_management.php" title="จัดการผู้ใช้" aria-label="จัดการผู้ใช้"><i class="bi bi-people-fill"></i></a>
                <a class="nav-link" href="ml_risk.php" title="Risk Dashboard" aria-label="Risk Dashboard"><i class="bi bi-shield-exclamation"></i></a>
            <?php endif; ?>
                <a class="nav-link" href="notifications.php" title="แจ้งเตือน" aria-label="แจ้งเตือน"><i class="bi bi-bell-fill"></i><span class="notification-dot"></span></a>
            </li>
            <li class="nav-item user-greeting" title="<?php echo e($_SESSION['fullname']); ?>"><i class="bi bi-person-circle"></i><span class="d-none d-xl-inline"><?php echo e($_SESSION['fullname']); ?></span></li>
            <li class="nav-item icon-nav"><a class="icon-button" href="logout.php" title="ออกจากระบบ" aria-label="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></a></li>
        <?php else: ?>
            <li class="nav-item icon-nav"><a class="icon-button" href="login.php" title="เข้าสู่ระบบ" aria-label="เข้าสู่ระบบ"><i class="bi bi-box-arrow-in-right"></i></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<div class="container my-4" style="min-height: 75vh;">