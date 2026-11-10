<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'], true)) { header('Location: login.php'); exit(); }
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$course_stmt = $pdo->prepare("SELECT c.*, u.fullname AS teacher_name FROM courses c JOIN users u ON u.user_id = c.teacher_id WHERE c.course_id = ?");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();
if (!$course || ($_SESSION['role'] === 'teacher' && $course['teacher_id'] != $_SESSION['user_id'])) { http_response_code(403); exit('ไม่มีสิทธิ์ดูรายงานนี้'); }
$stmt = $pdo->prepare("SELECT u.user_id, u.fullname, COUNT(DISTINCT sp.progress_id) AS completed_steps, ROUND(AVG(CASE WHEN er.total_score > 0 THEN er.score / er.total_score * 100 END), 1) AS average_score FROM users u JOIN course_agreements ca ON ca.student_id = u.user_id AND ca.course_id = ? LEFT JOIN units un ON un.course_id = ? LEFT JOIN student_progress sp ON sp.student_id = u.user_id AND sp.unit_id = un.unit_id LEFT JOIN exams ex ON ex.unit_id = un.unit_id LEFT JOIN exam_results er ON er.student_id = u.user_id AND er.exam_id = ex.exam_id WHERE u.role = 'student' GROUP BY u.user_id, u.fullname ORDER BY u.fullname");
$stmt->execute([$course_id, $course_id]);
$students = $stmt->fetchAll();
include 'header.php';
?>
<h3 class="text-primary mb-1">รายงานผู้เรียน</h3>
<p class="text-muted"><?php echo htmlspecialchars($course['title']); ?></p>
<div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>ผู้เรียน</th><th>จำนวน progress</th><th>คะแนนเฉลี่ย</th><th></th></tr></thead><tbody>
<?php foreach ($students as $student): ?><tr><td><?php echo htmlspecialchars($student['fullname']); ?></td><td><?php echo (int)$student['completed_steps']; ?></td><td><?php echo $student['average_score'] === null ? '-' : $student['average_score'] . '%'; ?></td><td><a class="btn btn-sm btn-outline-primary" href="certificate.php?course_id=<?php echo (int)$course_id; ?>&student_id=<?php echo (int)$student['user_id']; ?>">ตรวจ certificate</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php include 'footer.php'; ?>