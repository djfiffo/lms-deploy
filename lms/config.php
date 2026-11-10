<?php
require_once __DIR__ . '/services/ExternalServiceClient.php';

$host = getenv('LMS_DB_HOST');
$user = getenv('LMS_DB_USER');
$pass = getenv('LMS_DB_PASS');
$dbname = getenv('LMS_DB_NAME');

if (!$host || !$user || $pass === false || $pass === '' || !$dbname) {
    http_response_code(503);
    die("Database configuration is incomplete.");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    die("Database connection failed.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);
    session_start();
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function video_embed_url($value) {
    $value = trim((string)$value);
    if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $value, $match)) {
        $value = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    }
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) {
        return null;
    }

    $parts = parse_url($value);
    $host = strtolower($parts['host'] ?? '');
    $path = trim($parts['path'] ?? '', '/');
    parse_str($parts['query'] ?? '', $query);

    if ($host === 'youtu.be') {
        $video_id = explode('/', $path)[0];
        return $video_id !== '' ? 'https://www.youtube.com/embed/' . rawurlencode($video_id) : null;
    }

    if ($host === 'youtube.com' || $host === 'www.youtube.com' || $host === 'm.youtube.com') {
        if (strpos($path, 'embed/') === 0) {
            $video_id = substr($path, 6);
        } elseif (strpos($path, 'shorts/') === 0) {
            $video_id = substr($path, 7);
        } else {
            $video_id = $query['v'] ?? '';
        }
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $video_id) ? 'https://www.youtube.com/embed/' . $video_id : null;
    }

    if ($host === 'vimeo.com' || $host === 'www.vimeo.com') {
        $video_id = preg_replace('/^.*\/([0-9]+)$/', '$1', $path);
        return preg_match('/^[0-9]+$/', $video_id) ? 'https://player.vimeo.com/video/' . $video_id : null;
    }

    if ($host === 'player.vimeo.com' && strpos($path, 'video/') === 0) {
        $video_id = substr($path, 6);
        return preg_match('/^[0-9]+$/', $video_id) ? 'https://player.vimeo.com/video/' . $video_id : null;
    }

    return null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('คำขอไม่ถูกต้อง กรุณาลองใหม่');
    }
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function require_roles(array $roles) {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าถึง');
    }
}

function password_matches($stored, $password) {
    return password_verify($password, $stored) || hash_equals((string)$stored, (string)$password);
}

function maybe_upgrade_password(PDO $pdo, array $user, $password) {
    if (!password_needs_rehash((string)$user['password'], PASSWORD_DEFAULT)) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
}

function log_activity($pdo, $user_id, $event_type, $page_url = null, $time_spent = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, event_type, page_url, time_spent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $event_type, $page_url, $time_spent]);
    } catch (PDOException $e) {
        // Activity logging remains optional for older installations.
    }
}

function log_learning_event($pdo, $user_id, $event_type, array $payload = [], $course_id = null, $unit_id = null) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO learning_events (user_id, course_id, unit_id, event_type, payload) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$user_id, $course_id, $unit_id, $event_type, json_encode($payload)]);
    } catch (PDOException $error) {
        // The core LMS remains usable before the ML migration is installed.
    }
}
?>