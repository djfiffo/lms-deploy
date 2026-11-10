<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    verify_csrf();
    $student_id = $_SESSION['user_id'];
    $unit_id = filter_input(INPUT_POST, 'unit_id', FILTER_VALIDATE_INT);
    $step_key = trim($_POST['step_key'] ?? '');

    if (!$unit_id || $step_key === '') {
        header("Location: student_dashboard.php");
        exit();
    }

    $access_stmt = $pdo->prepare(
        "SELECT u.unit_id
         FROM units u
         JOIN course_agreements ca ON ca.course_id = u.course_id AND ca.student_id = ?
         WHERE u.unit_id = ?"
    );
    $access_stmt->execute([$student_id, $unit_id]);
    if (!$access_stmt->fetchColumn()) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าถึงหน่วยการเรียนนี้');
    }

    if (!preg_match('/^(objectives|unit_review|topic_[0-9]+)$/', $step_key)) {
        http_response_code(400);
        exit('ขั้นตอนการเรียนไม่ถูกต้อง');
    }

    $unit_info = $pdo->prepare('SELECT course_id FROM units WHERE unit_id = ?');
    $unit_info->execute([$unit_id]);
    $course_id = (int)$unit_info->fetchColumn();
    if (!$course_id) {
        http_response_code(404);
        exit('ไม่พบหน่วยการเรียน');
    }

    $topic_check = $pdo->prepare("SELECT topic_id FROM unit_topics WHERE unit_id = ? ORDER BY topic_order, topic_id");
    $topic_check->execute([$unit_id]);
    $step_order = ['objectives'];
    $exam_check = $pdo->prepare("SELECT exam_type FROM exams WHERE unit_id = ? AND exam_type IN ('unit_pretest', 'unit_posttest') ORDER BY exam_id");
    $exam_check->execute([$unit_id]);
    $exam_types = $exam_check->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('unit_pretest', $exam_types, true)) {
        $step_order[] = 'unit_pretest';
    }
    foreach ($topic_check->fetchAll(PDO::FETCH_COLUMN) as $topic_id) {
        $step_order[] = 'topic_' . $topic_id;
    }
    $step_order[] = 'unit_review';
    if (in_array('unit_posttest', $exam_types, true)) {
        $step_order[] = 'unit_posttest';
    }
    $step_position = array_search($step_key, $step_order, true);
    if ($step_position === false) {
        http_response_code(400);
        exit('ขั้นตอนนี้ไม่มีอยู่ในหน่วยการเรียน');
    }

    if ($step_position > 0) {
        $previous_step = $step_order[$step_position - 1];
        $previous_stmt = $pdo->prepare("SELECT is_completed FROM student_progress WHERE student_id = ? AND unit_id = ? AND step_key = ?");
        $previous_stmt->execute([$student_id, $unit_id, $previous_step]);
        if (!(bool)$previous_stmt->fetchColumn()) {
            http_response_code(409);
            exit('กรุณาเรียนขั้นตอนก่อนหน้าให้เสร็จก่อน');
        }
    }

    $stmt = $pdo->prepare("INSERT INTO student_progress (student_id, unit_id, step_key, is_completed) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE is_completed = 1");
    $stmt->execute([$student_id, $unit_id, $step_key]);
    log_learning_event($pdo, $student_id, 'progress', ['step_key' => $step_key], null, $unit_id);

    $next_step = $step_order[$step_position + 1] ?? $step_key;
    header("Location: unit_player.php?unit_id=" . $unit_id . "&step=" . urlencode($next_step));
    exit();
}
?>