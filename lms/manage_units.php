<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) {
    header('Location: login.php');
    exit();
}

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
if (!$course_id) {
    header('Location: dashboard.php');
    exit();
}

$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();
if (!$course) {
    http_response_code(404);
    exit('ไม่พบรายวิชาที่ต้องการ');
}
if ($_SESSION['role'] === 'teacher' && (int)$course['teacher_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์จัดการรายวิชานี้');
}

$edit_unit_id = filter_input(INPUT_GET, 'edit_unit', FILTER_VALIDATE_INT);
$unit = ['unit_number' => '', 'title' => '', 'objectives' => '', 'description' => ''];
$error = '';
if ($edit_unit_id) {
    $unit_stmt = $pdo->prepare("SELECT * FROM units WHERE unit_id = ? AND course_id = ?");
    $unit_stmt->execute([$edit_unit_id, $course_id]);
    $unit = $unit_stmt->fetch();
    if (!$unit) {
        http_response_code(404);
        exit('ไม่พบหน่วยการเรียนที่ต้องการ');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
    $unit_number = filter_input(INPUT_POST, 'unit_number', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $objectives = trim($_POST['objectives'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (!$unit_number || $unit_number < 1 || $title === '') {
        $error = 'กรุณาระบุลำดับหน่วยและชื่อหน่วยให้ถูกต้อง';
        $unit = ['unit_id' => $unit_id, 'unit_number' => $unit_number, 'title' => $title, 'objectives' => $objectives, 'description' => $description];
        $edit_unit_id = $unit_id;
    } elseif ($unit_id) {
        $stmt = $pdo->prepare("UPDATE units SET unit_number = ?, title = ?, objectives = ?, description = ? WHERE unit_id = ? AND course_id = ?");
        $stmt->execute([$unit_number, $title, $objectives, $description, $unit_id, $course_id]);
        header('Location: manage_units.php?course_id=' . $course_id);
        exit();
    } else {
        $stmt = $pdo->prepare("INSERT INTO units (course_id, unit_number, title, objectives, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $unit_number, $title, $objectives, $description]);
        header('Location: manage_units.php?course_id=' . $course_id);
        exit();
    }
}

$units_stmt = $pdo->prepare("SELECT * FROM units WHERE course_id = ? ORDER BY unit_number, unit_id");
$units_stmt->execute([$course_id]);
$units = $units_stmt->fetchAll();
include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-primary">จัดการหน่วย: <?php echo e($course['title']); ?></h3>
    <div><a class="btn btn-outline-primary" href="manage_course.php?course_id=<?php echo (int)$course_id; ?>">แก้ไขรายวิชา</a> <a class="btn btn-outline-secondary" href="dashboard.php">กลับ</a></div>
</div>
<div class="card card-custom p-4 mb-4">
    <h5><?php echo $edit_unit_id ? 'แก้ไขหน่วยการเรียน' : 'เพิ่มหน่วยการเรียน'; ?></h5>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
    <form method="POST" class="row g-2">
        <?php echo csrf_field(); ?>
        <?php if ($edit_unit_id): ?><input type="hidden" name="unit_id" value="<?php echo (int)$edit_unit_id; ?>"><?php endif; ?>
        <div class="col-md-2"><input class="form-control" type="number" min="1" name="unit_number" placeholder="ลำดับ" required value="<?php echo e($unit['unit_number']); ?>"></div>
        <div class="col-md-4"><input class="form-control" name="title" placeholder="ชื่อหน่วย" required value="<?php echo e($unit['title']); ?>"></div>
        <div class="col-md-6"><input class="form-control" name="objectives" placeholder="วัตถุประสงค์" value="<?php echo e($unit['objectives'] ?? ''); ?>"></div>
        <div class="col-12"><textarea class="form-control" name="description" rows="4" placeholder="รายละเอียดหน่วย"><?php echo e($unit['description'] ?? ''); ?></textarea></div>
        <div class="col-12"><button class="btn btn-pastel"><?php echo $edit_unit_id ? 'บันทึกการแก้ไข' : 'บันทึกหน่วย'; ?></button><?php if ($edit_unit_id): ?> <a class="btn btn-outline-secondary" href="manage_units.php?course_id=<?php echo (int)$course_id; ?>">ยกเลิก</a><?php endif; ?></div>
    </form>
</div>
<div class="row">
    <?php foreach ($units as $item): ?>
    <div class="col-md-6 mb-3"><div class="card card-custom h-100"><div class="card-body">
        <span class="badge bg-pastel-blue text-dark">หน่วยที่ <?php echo (int)$item['unit_number']; ?></span>
        <h5><?php echo e($item['title']); ?></h5>
        <p class="text-muted"><?php echo e($item['description'] ?? ''); ?></p>
        <a class="btn btn-sm btn-outline-primary" href="manage_units.php?course_id=<?php echo (int)$course_id; ?>&amp;edit_unit=<?php echo (int)$item['unit_id']; ?>">แก้ไขหน่วย</a>
        <a class="btn btn-sm btn-pastel" href="manage_topics.php?unit_id=<?php echo (int)$item['unit_id']; ?>">จัดการเนื้อหา</a>
        <a class="btn btn-sm btn-outline-primary" href="manage_exams.php?unit_id=<?php echo (int)$item['unit_id']; ?>">จัดการข้อสอบ</a>
    </div></div></div>
    <?php endforeach; ?>
</div>
<?php include 'footer.php'; ?>
