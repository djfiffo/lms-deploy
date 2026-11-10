<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) {
    header('Location: login.php');
    exit();
}

$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$error = '';
$course = ['course_code' => '', 'title' => '', 'description' => '', 'learning_objectives' => '', 'course_video_url' => '', 'teacher_id' => null];

if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) {
        http_response_code(404);
        exit('ไม่พบรายวิชาที่ต้องการ');
    }
    if ($_SESSION['role'] === 'teacher' && (int)$course['teacher_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์จัดการรายวิชานี้');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        if (!$course_id || !hash_equals((string)$course['course_code'], trim($_POST['confirm_course_code'] ?? ''))) {
            $error = 'กรุณาพิมพ์รหัสวิชาให้ตรงกันเพื่อยืนยันการลบ';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
                $stmt->execute([$course_id]);
                header('Location: dashboard.php');
                exit();
            } catch (PDOException $exception) {
                error_log('Unable to delete course ' . $course_id . ': ' . $exception->getMessage());
                $error = 'ไม่สามารถลบรายวิชาได้ กรุณาตรวจสอบข้อมูลที่เชื่อมโยงหรือติดต่อผู้ดูแลระบบ';
            }
        }
    } else {
        $course['course_code'] = trim($_POST['course_code'] ?? '');
        $course['title'] = trim($_POST['title'] ?? '');
        $course['description'] = trim($_POST['description'] ?? '');
        $course['learning_objectives'] = trim($_POST['learning_objectives'] ?? '');
        $course['course_video_url'] = trim($_POST['course_video_url'] ?? '');
        $teacher_id = $_SESSION['role'] === 'teacher' ? (int)$_SESSION['user_id'] : (int)($_POST['teacher_id'] ?? 0);

        $teacher_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'teacher'");
        $teacher_stmt->execute([$teacher_id]);
        if ($course['course_code'] === '' || $course['title'] === '' || !$teacher_stmt->fetch() ||
            ($course['course_video_url'] !== '' && !video_embed_url($course['course_video_url']))) {
            $error = 'กรุณากรอกรหัสวิชา ชื่อวิชา ผู้สอนให้ถูกต้อง และตรวจสอบ URL วิดีโอ';
        } else {
            if ($course_id) {
                $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, title = ?, description = ?, learning_objectives = ?, course_video_url = ?, teacher_id = ? WHERE course_id = ?");
                $stmt->execute([$course['course_code'], $course['title'], $course['description'], $course['learning_objectives'], $course['course_video_url'], $teacher_id, $course_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO courses (course_code, title, description, learning_objectives, course_video_url, teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$course['course_code'], $course['title'], $course['description'], $course['learning_objectives'], $course['course_video_url'], $teacher_id]);
                $course_id = (int)$pdo->lastInsertId();
            }
            header('Location: manage_units.php?course_id=' . $course_id);
            exit();
        }
    }
}

if ($_SESSION['role'] === 'admin') {
    $teachers = $pdo->query("SELECT user_id, fullname FROM users WHERE role = 'teacher' ORDER BY fullname")->fetchAll();
} else {
    $teachers = [];
}
include 'header.php';
?>
<div class="row justify-content-center"><div class="col-md-8"><div class="card card-custom p-4">
    <h3 class="text-primary mb-3"><?php echo $course_id ? 'แก้ไขรายวิชา' : 'สร้างรายวิชาใหม่'; ?></h3>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
    <form method="POST"><?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save">
        <label class="form-label">รหัสวิชา</label><input class="form-control mb-3" name="course_code" required value="<?php echo e($course['course_code']); ?>">
        <label class="form-label">ชื่อรายวิชา</label><input class="form-control mb-3" name="title" required value="<?php echo e($course['title']); ?>">
        <label class="form-label">รายละเอียด</label><textarea class="form-control mb-3" name="description" rows="5"><?php echo e($course['description'] ?? ''); ?></textarea>
        <label class="form-label">วัตถุประสงค์การเรียนรู้</label><textarea class="form-control mb-3" name="learning_objectives" rows="5"><?php echo e($course['learning_objectives'] ?? ''); ?></textarea>
        <label class="form-label">URL คลิปแนะนำรายวิชา</label><input class="form-control mb-3" type="url" name="course_video_url" value="<?php echo e($course['course_video_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=...">
        <?php if ($_SESSION['role'] === 'admin'): ?><label class="form-label">ผู้สอน</label><select class="form-select mb-3" name="teacher_id" required><?php foreach ($teachers as $teacher): ?><option value="<?php echo (int)$teacher['user_id']; ?>" <?php echo (($course['teacher_id'] ?? 0) == $teacher['user_id']) ? 'selected' : ''; ?>><?php echo e($teacher['fullname']); ?></option><?php endforeach; ?></select><?php endif; ?>
        <button class="btn btn-pastel" type="submit">บันทึก</button> <a class="btn btn-outline-secondary" href="dashboard.php">ยกเลิก</a>
    </form>

    <?php if ($course_id): ?>
    <hr class="my-4">
    <div class="alert alert-warning">
        <strong>ลบรายวิชาอย่างถาวร</strong><br>
        การลบจะลบหน่วย บทเรียน ข้อสอบ ผลสอบ ความก้าวหน้า ใบประกาศนียบัตร และข้อมูลอื่นที่ผูกกับรายวิชานี้ด้วย ไม่สามารถกู้คืนได้
    </div>
    <form method="POST" onsubmit="return confirm('ยืนยันลบรายวิชาและข้อมูลที่เกี่ยวข้องทั้งหมดอย่างถาวรหรือไม่?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="delete">
        <label class="form-label" for="confirm_course_code">พิมพ์รหัสวิชา <strong><?php echo e($course['course_code']); ?></strong> เพื่อยืนยัน</label>
        <input class="form-control mb-3" id="confirm_course_code" name="confirm_course_code" required autocomplete="off">
        <button class="btn btn-danger" type="submit">ลบรายวิชาถาวร</button>
    </form>
    <?php endif; ?>
</div></div></div>
<?php include 'footer.php'; ?>
