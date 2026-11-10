<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) { header('Location: login.php'); exit(); }
$exam_id = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT); $stmt = $pdo->prepare("SELECT e.*, c.teacher_id FROM exams e JOIN courses c ON c.course_id = e.course_id WHERE e.exam_id = ?"); $stmt->execute([$exam_id]); $exam = $stmt->fetch();
if (!$exam || ($_SESSION['role'] === 'teacher' && $exam['teacher_id'] != $_SESSION['user_id'])) { http_response_code(403); exit('ไม่มีสิทธิ์'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM questions WHERE question_id = ? AND exam_id = ?')->execute([(int)$_POST['question_id'], $exam_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$exam_id, trim($_POST['question_text']), trim($_POST['option_a']), trim($_POST['option_b']), trim($_POST['option_c']), trim($_POST['option_d']), $_POST['correct_option']]);
    }
    header('Location: manage_questions.php?exam_id=' . $exam_id); exit();
}
$stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ? ORDER BY question_id"); $stmt->execute([$exam_id]); $questions = $stmt->fetchAll(); include 'header.php';
?><h3 class="text-primary">คำถาม: <?php echo htmlspecialchars($exam['title']); ?></h3><div class="card card-custom p-4 mb-4"><form method="POST"><?php echo csrf_field(); ?><textarea class="form-control mb-2" name="question_text" placeholder="คำถาม" required></textarea><div class="row g-2"><?php foreach (['a','b','c','d'] as $option): ?><div class="col-md-6"><input class="form-control" name="option_<?php echo $option; ?>" placeholder="ตัวเลือก <?php echo strtoupper($option); ?>" required></div><?php endforeach; ?></div><select class="form-select my-2" name="correct_option"><option>A</option><option>B</option><option>C</option><option>D</option></select><button class="btn btn-pastel">เพิ่มคำถาม</button></form></div><ol><?php foreach ($questions as $question): ?><li class="mb-2"><?php echo htmlspecialchars($question['question_text']); ?> <span class="badge bg-success">เฉลย <?php echo $question['correct_option']; ?></span></li><?php endforeach; ?></ol><?php include 'footer.php'; ?>