<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) {
    header('Location: login.php');
    exit();
}

$unit_id = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT);
$unit_stmt = $pdo->prepare("SELECT u.*, c.course_id, c.title AS course_title, c.teacher_id FROM units u JOIN courses c ON c.course_id = u.course_id WHERE u.unit_id = ?");
$unit_stmt->execute([$unit_id]);
$unit = $unit_stmt->fetch();
if (!$unit) {
    http_response_code(404);
    exit('ไม่พบหน่วยการเรียนที่ต้องการ');
}
if ($_SESSION['role'] === 'teacher' && (int)$unit['teacher_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์จัดการเนื้อหานี้');
}

$edit_topic_id = filter_input(INPUT_GET, 'topic_id', FILTER_VALIDATE_INT);
$topic = ['topic_order' => '', 'title' => '', 'content_type' => 'text', 'content' => '', 'video_links' => ''];
$error = '';
if ($edit_topic_id) {
    $topic_stmt = $pdo->prepare("SELECT * FROM unit_topics WHERE topic_id = ? AND unit_id = ?");
    $topic_stmt->execute([$edit_topic_id, $unit_id]);
    $existing_topic = $topic_stmt->fetch();
    if (!$existing_topic) {
        http_response_code(404);
        exit('ไม่พบหัวข้อที่ต้องการ');
    }
    $topic = $existing_topic;
    if ($topic['content_type'] === 'video') {
        $topic['video_links'] = $topic['content'];
        $topic['content'] = '';
    } else {
        preg_match_all('/\[VIDEO=([^\]\r\n]+)\]/', $topic['content'] ?? '', $matches);
        $topic['video_links'] = implode("\n", $matches[1]);
        $topic['content'] = trim(preg_replace('/\[VIDEO=[^\]\r\n]+\]/', '', $topic['content'] ?? ''));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $posted_topic_id = filter_input(INPUT_POST, 'topic_id', FILTER_VALIDATE_INT);
    if ($action === 'delete') {
        if ($posted_topic_id) {
            $stmt = $pdo->prepare('DELETE FROM unit_topics WHERE topic_id = ? AND unit_id = ?');
            $stmt->execute([$posted_topic_id, $unit_id]);
        }
        header('Location: manage_topics.php?unit_id=' . $unit_id);
        exit();
    }

    $topic = [
        'topic_id' => $posted_topic_id,
        'topic_order' => filter_input(INPUT_POST, 'topic_order', FILTER_VALIDATE_INT),
        'title' => trim($_POST['title'] ?? ''),
        'content_type' => $_POST['content_type'] ?? '',
        'content' => trim($_POST['content'] ?? ''),
        'video_links' => trim($_POST['video_links'] ?? '')
    ];
    $video_links = preg_split('/\r\n|\r|\n/', $topic['video_links']);
    $video_links = array_values(array_filter(array_map('trim', $video_links), function ($link) {
        return $link !== '';
    }));

    if (!$topic['topic_order'] || $topic['topic_order'] < 1 || $topic['title'] === '' ||
        !in_array($topic['content_type'], ['text', 'video', 'activity'], true)) {
        $error = 'กรุณาระบุลำดับ ชื่อหัวข้อ และประเภทเนื้อหาให้ถูกต้อง';
    } elseif ($topic['content_type'] === 'video' && (count($video_links) !== 1 || !video_embed_url($video_links[0]))) {
        $error = 'เนื้อหาวิดีโอต้องมี URL YouTube หรือ Vimeo ที่ถูกต้องเพียง 1 ลิงก์';
    } elseif ($topic['content_type'] !== 'video' && $topic['content'] === '') {
        $error = 'กรุณากรอกเนื้อหาหรือกิจกรรม';
    } else {
        $invalid_video = false;
        foreach ($video_links as $video_link) {
            if (!video_embed_url($video_link)) {
                $invalid_video = true;
                break;
            }
        }
        if ($invalid_video) {
            $error = 'พบ URL วิดีโอที่ไม่ถูกต้อง กรุณาใช้ลิงก์ YouTube หรือ Vimeo';
        } else {
            if ($topic['content_type'] === 'video') {
                $content = $video_links[0];
            } else {
                $video_markers = array_map(function ($link) {
                    return '[VIDEO=' . $link . ']';
                }, $video_links);
                $content = $topic['content'];
                if ($video_markers) {
                    $content = implode("\n", $video_markers) . "\n\n" . $content;
                }
            }

            if ($posted_topic_id) {
                $stmt = $pdo->prepare("UPDATE unit_topics SET topic_order = ?, title = ?, content_type = ?, content = ? WHERE topic_id = ? AND unit_id = ?");
                $stmt->execute([$topic['topic_order'], $topic['title'], $topic['content_type'], $content, $posted_topic_id, $unit_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO unit_topics (unit_id, topic_order, title, content_type, content) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$unit_id, $topic['topic_order'], $topic['title'], $topic['content_type'], $content]);
            }
            header('Location: manage_topics.php?unit_id=' . $unit_id);
            exit();
        }
    }
    $edit_topic_id = $posted_topic_id;
}

$stmt = $pdo->prepare("SELECT * FROM unit_topics WHERE unit_id = ? ORDER BY topic_order, topic_id");
$stmt->execute([$unit_id]);
$topics = $stmt->fetchAll();
include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="text-primary">เนื้อหา: <?php echo e($unit['title']); ?></h3>
    <a class="btn btn-outline-secondary" href="manage_units.php?course_id=<?php echo (int)$unit['course_id']; ?>">กลับไปยังหน่วย</a>
</div>
<div class="card card-custom p-4 mb-4">
    <h5><?php echo $edit_topic_id ? 'แก้ไขบทเรียน/เนื้อหา' : 'เพิ่มบทเรียน/เนื้อหา'; ?></h5>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
    <form method="POST" class="row g-2">
        <?php echo csrf_field(); ?>
        <?php if ($edit_topic_id): ?><input type="hidden" name="topic_id" value="<?php echo (int)$edit_topic_id; ?>"><?php endif; ?>
        <div class="col-md-2"><input class="form-control" type="number" min="1" name="topic_order" placeholder="ลำดับ" required value="<?php echo e($topic['topic_order']); ?>"></div>
        <div class="col-md-4"><input class="form-control" name="title" placeholder="ชื่อหัวข้อ" required value="<?php echo e($topic['title']); ?>"></div>
        <div class="col-md-3"><select class="form-select" name="content_type"><option value="text" <?php echo $topic['content_type'] === 'text' ? 'selected' : ''; ?>>ข้อความ</option><option value="video" <?php echo $topic['content_type'] === 'video' ? 'selected' : ''; ?>>วิดีโอ</option><option value="activity" <?php echo $topic['content_type'] === 'activity' ? 'selected' : ''; ?>>กิจกรรม</option></select></div>
        <div class="col-12"><label class="form-label">ลิงก์วิดีโอ (หนึ่งลิงก์ต่อบรรทัด)</label><textarea class="form-control" name="video_links" rows="2" placeholder="https://www.youtube.com/watch?v=..."><?php echo e($topic['video_links'] ?? ''); ?></textarea><small class="text-muted">รองรับ YouTube และ Vimeo; เว้นว่างได้ถ้าไม่มีวิดีโอ</small></div>
        <div class="col-12"><label class="form-label">เนื้อหาบทเรียนหรือกิจกรรม</label><textarea class="form-control" name="content" rows="8" placeholder="กรอกเนื้อหาเต็มของบทเรียน"><?php echo e($topic['content'] ?? ''); ?></textarea></div>
        <div class="col-12"><button class="btn btn-pastel"><?php echo $edit_topic_id ? 'บันทึกการแก้ไข' : 'เพิ่มหัวข้อ'; ?></button><?php if ($edit_topic_id): ?> <a class="btn btn-outline-secondary" href="manage_topics.php?unit_id=<?php echo (int)$unit_id; ?>">ยกเลิก</a><?php endif; ?></div>
    </form>
</div>
<div class="list-group">
    <?php foreach ($topics as $item): ?>
    <div class="list-group-item">
        <strong><?php echo (int)$item['topic_order']; ?>. <?php echo e($item['title']); ?></strong>
        <span class="badge bg-secondary float-end"><?php echo e($item['content_type']); ?></span>
        <div class="mt-2">
            <a class="btn btn-sm btn-outline-primary" href="manage_topics.php?unit_id=<?php echo (int)$unit_id; ?>&amp;topic_id=<?php echo (int)$item['topic_id']; ?>">แก้ไขเนื้อหา/วิดีโอ</a>
            <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันลบหัวข้อนี้หรือไม่?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="topic_id" value="<?php echo (int)$item['topic_id']; ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">ลบ</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php include 'footer.php'; ?>
