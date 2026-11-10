<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['reply' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน'], JSON_UNESCAPED_UNICODE); exit(); }
$payload = json_decode(file_get_contents('php://input'), true);
$message = trim($payload['message'] ?? '');
if ($message === '') { http_response_code(400); echo json_encode(['reply' => 'กรุณาพิมพ์คำถาม'], JSON_UNESCAPED_UNICODE); exit(); }
$context = '';
$course_id = filter_var($payload['course_id'] ?? 0, FILTER_VALIDATE_INT);
$unit_id = filter_var($payload['unit_id'] ?? 0, FILTER_VALIDATE_INT);
if ($course_id && $unit_id) {
    $context_stmt = $pdo->prepare(
        'SELECT c.title AS course_title, u.title AS unit_title, u.objectives, u.description
         FROM units u JOIN courses c ON c.course_id = u.course_id
         JOIN course_agreements ca ON ca.course_id = c.course_id AND ca.student_id = ?
         WHERE c.course_id = ? AND u.unit_id = ?'
    );
    $context_stmt->execute([$_SESSION['user_id'], $course_id, $unit_id]);
    $context_row = $context_stmt->fetch();
    if ($context_row) {
        $context = "\nบริบทบทเรียน: รายวิชา {$context_row['course_title']} หน่วย {$context_row['unit_title']} "
            . "วัตถุประสงค์: {$context_row['objectives']} รายละเอียด: {$context_row['description']}";
    }
}
$ai_url = getenv('LMS_CHATBOT_URL') ?: '';
$ai_key = getenv('LMS_CHATBOT_API_KEY') ?: '';
$provider_error = '';
if ($ai_url === '') {
    $provider_error = 'ยังไม่ได้โหลดค่า LMS_CHATBOT_URL จาก Apache';
} elseif (!function_exists('curl_init')) {
    $provider_error = 'PHP ยังไม่ได้เปิดใช้งาน cURL';
} else {
    $request = [
        'model' => getenv('LMS_CHATBOT_MODEL') ?: 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'คุณเป็นผู้ช่วยสอน LMS ตอบภาษาไทย กระชับ อ้างอิงเฉพาะบริบทบทเรียน ห้ามแต่งคะแนนหรือเฉลยข้อสอบ' . $context],
            ['role' => 'user', 'content' => $message],
        ],
        'temperature' => 0.2,
    ];
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($ai_key !== '') $headers[] = 'Authorization: Bearer ' . $ai_key;
    $handle = curl_init($ai_url);
    $ca_file = ini_get('curl.cainfo');
    if ($ca_file === '' && DIRECTORY_SEPARATOR === '\\') {
        $local_ca_file = 'C:\\AppServ\\php7\\extras\\ssl\\cacert.pem';
        if (is_file($local_ca_file)) {
            $ca_file = $local_ca_file;
        }
    }
    $curl_options = [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($request),
        CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
    ];
    if ($ca_file !== '') {
        $curl_options[CURLOPT_CAINFO] = $ca_file;
    }
    curl_setopt_array($handle, $curl_options);
    $body = curl_exec($handle);
    $curl_error = curl_error($handle);
    $curl_errno = curl_errno($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    $decoded = json_decode((string)$body, true);
    if ($status >= 200 && $status < 300 && is_array($decoded)) {
        $reply = $decoded['choices'][0]['message']['content'] ?? $decoded['reply'] ?? '';
        if (is_string($reply) && trim($reply) !== '') {
            echo json_encode(['reply' => trim($reply), 'mode' => 'ai'], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
    if ($curl_error !== '') {
        $provider_error = 'เชื่อมต่อผู้ให้บริการ AI ไม่สำเร็จ (cURL ' . $curl_errno . ')';
    } elseif ($status > 0 && ($status < 200 || $status >= 300)) {
        $provider_error = 'ผู้ให้บริการ AI ตอบกลับ HTTP ' . $status;
    } else {
        $provider_error = 'ผู้ให้บริการ AI ส่งข้อมูลไม่ถูกต้อง';
    }
}
$reply = 'ลองตรวจสอบวัตถุประสงค์ เนื้อหา และแบบทดสอบของหน่วยปัจจุบันตามลำดับ หากยังมีข้อสงสัยกรุณาติดต่อผู้สอน';
if (preg_match('/(สวัสดี|hello|hi)/iu', $message)) { $reply = 'สวัสดีครับ มีคำถามเกี่ยวกับบทเรียนหรือแบบทดสอบสามารถพิมพ์ถามได้เลย'; }
elseif (preg_match('/(คะแนน|สอบ|แบบทดสอบ)/iu', $message)) { $reply = 'ระบบจะบันทึกผลหลังส่งคำตอบ และปลดล็อกขั้นตอนถัดไปเมื่อผ่านเกณฑ์'; }
elseif (preg_match('/(เนื้อหา|บทเรียน|เรียน)/iu', $message)) { $reply = 'กรุณาเรียนตามลำดับที่แสดงในแถบด้านซ้ายของหน่วยการเรียน'; }
echo json_encode([
    'reply' => $reply,
    'mode' => 'fallback',
    'provider_error' => $provider_error,
], JSON_UNESCAPED_UNICODE);