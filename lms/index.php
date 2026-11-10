<?php
require_once 'config.php';
include 'header.php';

// ดึงข้อมูลรายวิชาทั้งหมดมาแสดงในหน้าแรก
$stmt = $pdo->query("SELECT c.*, u.fullname as teacher_name FROM courses c JOIN users u ON c.teacher_id = u.user_id ORDER BY c.course_id DESC");
$courses = $stmt->fetchAll();
?>

<!-- Hero / Banner Section (สีฟ้าพาสเทล) -->
<div class="row align-items-center bg-pastel-blue p-4 p-md-5 rounded-4 mb-5 shadow-sm">
    <div class="col-md-7">
        <span class="badge bg-warning text-dark fs-6 mb-2">ยินดีต้อนรับสู่ระบบการเรียนออนไลน์</span>
        <h1 class="display-5 fw-bold text-dark mb-3">Hangyodon University</h1>
        <p class="lead text-secondary mb-4">
            คลังแห่งการเรียนรู้ยุคใหม่ เสริมสร้างทักษะด้วยบทเรียนออนไลน์ที่เข้าถึงได้ทุกที่ทุกเวลา 
            ตามลำดับขั้นตอนการเรียนรู้ที่ถูกออกแบบมาเพื่อคุณโดยเฉพาะ
        </p>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="btn btn-pastel-yellow btn-lg px-4 me-2 shadow-sm">เข้าสู่ระบบการเรียน</a>
        <?php else: ?>
            <?php if ($_SESSION['role'] === 'student'): ?>
                <a href="student_dashboard.php" class="btn btn-pastel-yellow btn-lg px-4 me-2 shadow-sm">เข้าสู่ห้องเรียนของฉัน ➔</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-pastel-yellow btn-lg px-4 me-2 shadow-sm">เข้าสู่ระบบจัดการรายวิชา ➔</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="col-md-5 text-center mt-4 mt-md-0">
        <div class="p-4 bg-white rounded-circle d-inline-block shadow-sm">
            <span style="font-size: 80px;">📚🎓</span>
        </div>
    </div>
</div>

<!-- Features Highlight (โทนเหลืองพาสเทล) -->
<div class="row mb-5 text-center">
    <div class="col-md-4 mb-3">
        <div class="card card-custom p-3 bg-pastel-yellow h-100">
            <div class="fs-1 mb-2">🎯</div>
            <h5>ลำดับการเรียนรู้ที่เป็นระบบ</h5>
            <p class="text-muted small mb-0">มีขั้นตอนชัดเจน ตั้งแต่วัตถุประสงค์ แบบทดสอบก่อนเรียน เนื้อหา จนถึงแบบทดสอบหลังเรียน</p>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-custom p-3 bg-pastel-yellow h-100">
            <div class="fs-1 mb-2">🤖</div>
            <h5>ระบบผู้ช่วย AI Chatbot</h5>
            <p class="text-muted small mb-0">คอยให้คำแนะนำ คำปรึกษา และตอบข้อสงสัยในการเรียนได้ตลอด 24 ชั่วโมง</p>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card card-custom p-3 bg-pastel-yellow h-100">
            <div class="fs-1 mb-2">📜</div>
            <h5>ใบเกียรติบัตรออนไลน์</h5>
            <p class="text-muted small mb-0">รับใบเกียรติบัตรรับรองทันที เมื่อเรียนจบหลักสูตรและผ่านเกณฑ์การสอบ</p>
        </div>
    </div>
</div>

<!-- Courses Section -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="text-primary fw-bold">📖 รายวิชาเปิดสอน</h3>
</div>

<div class="row">
    <?php if (count($courses) > 0): ?>
        <?php foreach ($courses as $course): ?>
            <div class="col-md-4 mb-4">
                <div class="card card-custom h-100 d-flex flex-column">
                    <div class="card-body">
                        <span class="badge bg-pastel-blue text-dark mb-2"><?php echo htmlspecialchars($course['course_code']); ?></span>
                        <h5 class="card-title fw-bold text-dark"><?php echo htmlspecialchars($course['title']); ?></h5>
                        <p class="card-text text-muted small">
                            <?php echo htmlspecialchars(mb_strimwidth($course['description'] ?? '', 0, 100, "...")); ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                        <hr class="mt-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">👨‍🏫 <?php echo htmlspecialchars($course['teacher_name']); ?></small>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                                <a href="course_agreements.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-sm btn-pastel">เข้าเรียน</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-sm btn-outline-primary">เข้าสู่ระบบเพื่อเรียน</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center">ยังไม่มีรายวิชาเปิดสอนในขณะนี้</div>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>