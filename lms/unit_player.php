<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$student_id = $_SESSION['user_id'];
$unit_id = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT);

if (!$unit_id) {
    header("Location: student_dashboard.php");
    exit();
}

$unit_stmt = $pdo->prepare(
    "SELECT u.*, c.title AS course_title, c.course_id
     FROM units u
     JOIN courses c ON c.course_id = u.course_id
     JOIN course_agreements ca ON ca.course_id = c.course_id AND ca.student_id = ?
     WHERE u.unit_id = ?"
);
$unit_stmt->execute([$student_id, $unit_id]);
$unit = $unit_stmt->fetch();

if (!$unit) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงหน่วยการเรียนนี้');
}
$course_pretest = $pdo->prepare(
    "SELECT e.exam_id FROM exams e WHERE e.course_id = ? AND e.exam_type = 'course_pretest' ORDER BY e.exam_id DESC LIMIT 1"
);
$course_pretest->execute([$unit['course_id']]);
$course_pretest_id = $course_pretest->fetchColumn();
if ($course_pretest_id) {
    $pretest_result = $pdo->prepare('SELECT 1 FROM exam_results WHERE student_id = ? AND exam_id = ? LIMIT 1');
    $pretest_result->execute([$student_id, $course_pretest_id]);
    if (!$pretest_result->fetchColumn()) {
        header('Location: course_pretest.php?course_id=' . (int)$unit['course_id']);
        exit();
    }
}
log_activity($pdo, $student_id, 'view_unit', 'unit_player.php?unit_id=' . $unit_id);
log_learning_event($pdo, $student_id, 'content_viewed', ['minutes' => 0], (int)$unit['course_id'], $unit_id);

$sequence = [
    'objectives' => 'วัตถุประสงค์การเรียนรู้'
];

$topic_stmt = $pdo->prepare("SELECT * FROM unit_topics WHERE unit_id = ? ORDER BY topic_order, topic_id");
$topic_stmt->execute([$unit_id]);
$topics = $topic_stmt->fetchAll();
$exam_stmt = $pdo->prepare("SELECT * FROM exams WHERE unit_id = ? AND exam_type IN ('unit_pretest', 'unit_posttest', 'unit_review') ORDER BY exam_id");
$exam_stmt->execute([$unit_id]);
$unit_exams = $exam_stmt->fetchAll();
$exam_by_type = [];
foreach ($unit_exams as $exam) {
    $exam_by_type[$exam['exam_type']] = $exam;
}

if (isset($exam_by_type['unit_pretest'])) {
    $sequence['unit_pretest'] = 'ข้อสอบก่อนเรียนประจำหน่วย';
}
foreach ($topics as $topic) {
    $sequence['topic_' . $topic['topic_id']] = $topic['title'];
}
$sequence['unit_review'] = 'กิจกรรมทบทวนความรู้ประจำหน่วย';
if (isset($exam_by_type['unit_posttest'])) {
    $sequence['unit_posttest'] = 'ข้อสอบหลังเรียนประจำหน่วย';
}

$active_step = $_GET['step'] ?? 'objectives';
if (!isset($sequence[$active_step])) {
    $active_step = 'objectives';
}

// ฟังก์ชันดึงสถานะความก้าวหน้า
function isStepDone($pdo, $student_id, $unit_id, $step) {
    $stmt = $pdo->prepare("SELECT is_completed FROM student_progress WHERE student_id = ? AND unit_id = ? AND step_key = ?");
    $stmt->execute([$student_id, $unit_id, $step]);
    return (bool)$stmt->fetchColumn();
}

include 'header.php';
?>

<div class="row">
    <!-- Sidebar ลำดับขั้นตอน -->
    <div class="col-md-4 mb-3">
        <div class="list-group shadow-sm">
            <div class="list-group-item bg-pastel-blue fw-bold text-dark">📌 โครงสร้างหน่วยการเรียนรู้</div>
            <?php 
            $unlocked = true;
            foreach ($sequence as $key => $title): 
                $done = isStepDone($pdo, $student_id, $unit_id, $key);
            ?>
                <a href="<?php echo $unlocked ? "?unit_id=$unit_id&step=$key" : "#"; ?>" 
                   class="list-group-item list-group-item-action <?php echo ($active_step === $key)?'bg-pastel-yellow fw-bold':''; ?> <?php echo !$unlocked ? 'disabled text-muted' : ''; ?>">
                   <?php echo $done ? '✅' : ($unlocked ? '📖' : '🔒'); ?> <?php echo $title; ?>
                </a>
            <?php 
                if (!$done) { $unlocked = false; }
            endforeach; 
            ?>
        </div>
    </div>

    <!-- ส่วนเนื้อหาหลัก -->
    <div class="col-md-8">
        <div class="card card-custom p-4">
            <h4 class="text-primary"><?php echo htmlspecialchars($sequence[$active_step]); ?></h4>
            <hr>
            <div class="my-3">
                <?php if ($active_step === 'objectives'): ?>
                    <p><?php echo nl2br(htmlspecialchars($unit['objectives'] ?? 'ยังไม่มีวัตถุประสงค์การเรียนรู้')); ?></p>
                <?php elseif ($active_step === 'unit_pretest' || $active_step === 'unit_posttest'): ?>
                    <?php $active_exam = $exam_by_type[$active_step]; ?>
                    <?php
                    $exam_result_stmt = $pdo->prepare(
                        "SELECT score, total_score, passed
                         FROM exam_results
                         WHERE student_id = ? AND exam_id = ?
                         ORDER BY completed_at DESC, result_id DESC
                         LIMIT 1"
                    );
                    $exam_result_stmt->execute([$student_id, $active_exam['exam_id']]);
                    $exam_result = $exam_result_stmt->fetch();
                    ?>
                    <?php if ($exam_result): ?>
                        <div class="alert <?php echo $exam_result['passed'] ? 'alert-success' : 'alert-warning'; ?>">
                            ผลล่าสุด <?php echo (int)$exam_result['score']; ?>/<?php echo (int)$exam_result['total_score']; ?>
                            <?php echo $exam_result['passed'] ? 'ผ่านเกณฑ์แล้ว' : 'ยังไม่ผ่าน สามารถทำใหม่ได้'; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!$exam_result || !$exam_result['passed']): ?>
                        <p>กรุณาทำ<?php echo htmlspecialchars($sequence[$active_step]); ?>เพื่อดำเนินการต่อ</p>
                        <a class="btn btn-pastel" href="exam.php?exam_id=<?php echo (int)$active_exam['exam_id']; ?>">
                            <?php echo $exam_result ? 'ทำแบบทดสอบใหม่' : 'เริ่มทำแบบทดสอบ'; ?>
                        </a>
                    <?php endif; ?>
                <?php elseif (strpos($active_step, 'topic_') === 0): ?>
                    <?php $active_topic = null; foreach ($topics as $topic) { if ($active_step === 'topic_' . $topic['topic_id']) { $active_topic = $topic; break; } } ?>
                    <?php if ($active_topic): ?>
                        <?php if ($active_topic['content_type'] === 'video'): ?>
                            <?php
                            $video = video_embed_url($active_topic['content']);
                            if ($video):
                            ?>
                            <div class="ratio ratio-16x9 mb-3"><iframe src="<?php echo e($video); ?>" title="<?php echo e($active_topic['title']); ?>" allowfullscreen></iframe></div>
                            <?php else: ?><div class="alert alert-warning">รูปแบบวิดีโอไม่ปลอดภัย</div><?php endif; ?>
                        <?php else: ?>
                            <?php
                            $topic_content = $active_topic['content'] ?? 'ยังไม่มีเนื้อหา';
                            $video_urls = [];
                            $topic_content = preg_replace_callback(
                                '/\[VIDEO=([^\]\r\n]+)\]/',
                                function ($matches) use (&$video_urls) {
                                    $video_url = video_embed_url($matches[1]);
                                    if ($video_url) {
                                        $video_urls[] = $video_url;
                                    }
                                    return '';
                                },
                                $topic_content
                            );
                            ?>
                            <?php foreach ($video_urls as $video_url): ?>
                                <?php if ($video_url): ?><div class="ratio ratio-16x9 mb-3"><iframe src="<?php echo e($video_url); ?>" title="<?php echo e($active_topic['title']); ?>" allowfullscreen loading="lazy"></iframe></div><?php endif; ?>
                            <?php endforeach; ?>
                            <div><?php echo nl2br(htmlspecialchars(trim($topic_content) ?: 'ยังไม่มีเนื้อหา')); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p>กรุณาทบทวนเนื้อหาทั้งหมดของหน่วยนี้ แล้วกดบันทึกเพื่อดำเนินการต่อ</p>
                <?php endif; ?>
            </div>
            
            <?php if (in_array($active_step, ['objectives', 'unit_review'], true) || strpos($active_step, 'topic_') === 0): ?>
                <form action="complete_step.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="unit_id" value="<?php echo $unit_id; ?>">
                    <input type="hidden" name="step_key" value="<?php echo e($active_step); ?>">
                    <button type="submit" class="btn btn-pastel float-end">บันทึกและไปขั้นตอนถัดไป ➔</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    #chat-body { background: #f7fbff !important; }
    .chat-message { display: flex; margin-bottom: .75rem; }
    .chat-message.user { justify-content: flex-end; }
    .chat-bubble { max-width: 88%; padding: .6rem .8rem; border-radius: 14px; line-height: 1.55; white-space: pre-line; overflow-wrap: anywhere; }
    .chat-message.user .chat-bubble { background: #dcecff; color: #29415f; border-bottom-right-radius: 4px; }
    .chat-message.bot .chat-bubble { background: #fff; color: #29415f; border: 1px solid #e1ebf7; border-bottom-left-radius: 4px; }
    .chat-label { display: block; font-size: .72rem; font-weight: 600; color: #6d86a3; margin-bottom: .15rem; }
</style>

<!-- Chatbot Floating Widget -->
<div class="position-fixed bottom-0 end-0 m-3">
    <button class="btn btn-light border rounded-pill px-3 py-2 shadow-sm d-flex align-items-center gap-2"
            data-bs-toggle="modal" data-bs-target="#chatModal" aria-label="เปิดผู้ช่วย AI">
        <span class="text-primary fw-semibold">ถาม AI</span>
        <span aria-hidden="true">✦</span>
    </button>
</div>

<!-- Modal Chatbot -->
<div class="modal fade" id="chatModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">ถาม AI</h5>
          <small class="text-muted">ผู้ช่วยประจำบทเรียนนี้</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light" id="chat-body" style="height: 260px; overflow-y: auto;">
        <p class="text-muted mb-0">ถามเกี่ยวกับเนื้อหา บทเรียน หรือแบบทดสอบได้เลย</p>
      </div>
      <div class="modal-footer">
        <div class="input-group">
          <input type="text" id="chat-input" class="form-control" placeholder="พิมพ์คำถาม..." autocomplete="off">
          <button type="button" class="btn btn-pastel px-3" onclick="sendChat()">ส่ง</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function sendChat() {
    let msg = document.getElementById('chat-input').value;
    if(!msg) return;
    let body = document.getElementById('chat-body');
    body.innerHTML += `<div class="chat-message user"><div class="chat-bubble"><span class="chat-label">คุณ</span>${escapeHtml(msg)}</div></div>`;
    
    fetch('chatbot_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({message: msg, course_id: <?php echo (int)$unit['course_id']; ?>, unit_id: <?php echo (int)$unit_id; ?>})
    })
    .then(res => res.json().then(data => ({ok: res.ok, data: data})))
    .then(data => {
        let reply = formatReply(data.data.reply || 'ไม่สามารถรับคำตอบได้ในขณะนี้');
        const safeReply = escapeHtml(reply);
        let statusMessage = '';
        if (data.data.provider_error) {
            statusMessage = `\n\n${data.data.provider_error} ระบบจึงใช้คำตอบสำรอง`;
        }
        body.innerHTML += `<div class="chat-message bot"><div class="chat-bubble"><span class="chat-label">ผู้ช่วย AI</span>${safeReply}${escapeHtml(statusMessage)}</div></div>`;
        document.getElementById('chat-input').value = '';
        body.scrollTop = body.scrollHeight;
    })
    .catch(() => {
        body.innerHTML += '<div class="chat-message bot"><div class="chat-bubble text-danger"><span class="chat-label">ผู้ช่วย AI</span>เชื่อมต่อไม่ได้ กรุณาลองใหม่</div></div>';
    });
}
function formatReply(value) {
    return String(value)
        .replace(/&lt;br\s*\/?&gt;/gi, '\n')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/?p>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function(char) {
        return {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[char];
    });
}
document.getElementById('chat-input').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        sendChat();
    }
});
</script>

<?php include 'footer.php'; ?>