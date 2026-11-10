<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$stmt = $pdo->prepare("SELECT c.* FROM courses c JOIN course_agreements ca ON ca.course_id = c.course_id AND ca.student_id = ? WHERE c.course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]); $course = $stmt->fetch();
if (!$course) { http_response_code(403); exit('ไม่มีสิทธิ์เข้าถึงรายวิชานี้'); }
$pretest = $pdo->prepare(
    "SELECT 1 FROM exam_results er JOIN exams e ON e.exam_id = er.exam_id
     WHERE er.student_id = ? AND e.course_id = ? AND e.exam_type = 'course_pretest' LIMIT 1"
);
$pretest->execute([$_SESSION['user_id'], $course_id]);
if (!$pretest->fetchColumn()) { http_response_code(403); exit('กรุณาทำแบบทดสอบก่อนเรียนรายวิชาก่อน'); }
$units = $pdo->prepare(
    "SELECT COUNT(*) AS unit_count, SUM(CASE WHEN e.exam_id IS NOT NULL AND er.passed = 1 THEN 1 ELSE 0 END) AS passed_count
     FROM units u
     LEFT JOIN exams e ON e.unit_id = u.unit_id AND e.exam_type = 'unit_posttest'
     LEFT JOIN exam_results er ON er.exam_id = e.exam_id AND er.student_id = ? AND er.passed = 1
     WHERE u.course_id = ?"
);
$units->execute([$_SESSION['user_id'], $course_id]); $unit_status = $units->fetch();
if ((int)$unit_status['unit_count'] === 0 || (int)$unit_status['passed_count'] < (int)$unit_status['unit_count']) {
    http_response_code(403); exit('กรุณาผ่านแบบทดสอบหลังเรียนของทุกหน่วยก่อนทบทวนรายวิชา');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $save = $pdo->prepare("INSERT INTO course_progress (student_id, course_id, step_key, is_completed, completed_at) VALUES (?, ?, 'course_review', 1, NOW()) ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = NOW()");
    $save->execute([$_SESSION['user_id'], $course_id]);
    header('Location: course_dashboard.php?course_id=' . $course_id); exit();
}
include 'header.php';
?>
<div class="card card-custom p-4">
    <h3 class="text-primary">ทบทวนความรู้ประจำวิชา: <?php echo e($course['title']); ?></h3>
    <h5>สรุปภาพรวม</h5>
    <p>ฐานข้อมูลใช้จัดเก็บข้อมูลอย่างเป็นระบบ โดย DBMS ช่วยกำหนดโครงสร้าง ควบคุมสิทธิ์ ดูแลความถูกต้อง และค้นคืนข้อมูล โมเดลเชิงสัมพันธ์แทนข้อมูลด้วยตาราง แถว และคอลัมน์ เชื่อมตารางด้วย Primary Key และ Foreign Key การออกแบบที่ดีเริ่มจากวิเคราะห์ข้อกำหนดและ ERD แล้วแปลงเอนทิตี/ความสัมพันธ์เป็นตารางพร้อมกำหนดชนิดข้อมูลและข้อบังคับ</p>
    <h5>ทบทวน SQL และ MySQL</h5>
    <p>ใช้ DDL เช่น CREATE, ALTER และ DROP กำหนดโครงสร้าง ใช้ DML เช่น INSERT, UPDATE และ DELETE เปลี่ยนข้อมูล และใช้ SELECT อ่านข้อมูล WHERE กรองแถว ORDER BY เรียง GROUP BY รวมกลุ่ม HAVING กรองผลรวม JOIN เชื่อมตาราง และฟังก์ชัน COUNT, SUM, AVG สรุปข้อมูล ควรใช้เงื่อนไข WHERE ใน UPDATE/DELETE เพื่อไม่เปลี่ยนข้อมูลทุกแถวโดยไม่ตั้งใจ</p>
    <h5>ทบทวน phpMyAdmin</h5>
    <p>phpMyAdmin เป็นเครื่องมือเว็บสำหรับจัดการ MySQL/MariaDB สามารถสร้างฐานข้อมูล ตาราง และดัชนี ดู/นำเข้าข้อมูล และรัน SQL ได้ แต่คำสั่ง SQL ที่รันผ่านเครื่องมือนี้ทำงานจริงกับฐานข้อมูล จึงควรเลือกฐานข้อมูลให้ถูก ตรวจคำสั่งก่อนยืนยัน และสำรองข้อมูลก่อนการเปลี่ยนแปลงสำคัญ</p>
    <h5>ทบทวน PHP เชื่อมต่อฐานข้อมูล</h5>
    <p>PHP ติดต่อ MySQL ได้ด้วย PDO หรือ MySQLi ใช้ prepared statements แยกคำสั่งออกจากค่าผู้ใช้ ป้องกัน SQL injection ตรวจสอบข้อมูลฝั่งเซิร์ฟเวอร์ ใช้สิทธิ์ฐานข้อมูลเท่าที่จำเป็น และจัดการข้อผิดพลาดโดยไม่เปิดเผยรหัสผ่านหรือรายละเอียดระบบให้ผู้ใช้</p>
    <form method="POST" class="mt-3"><?php echo csrf_field(); ?><p>อ่านสรุปครบแล้วจึงบันทึก เพื่อปลดล็อกแบบทดสอบหลังเรียนรายวิชา</p><button class="btn btn-pastel">บันทึกการทบทวน</button></form>
</div>
<?php include 'footer.php'; ?>
