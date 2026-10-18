<?php
require_once __DIR__ . '/../config/app.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
set_exception_handler(static function (Throwable $e): void {
    error_log('Unhandled application error: ' . get_class($e));
    http_response_code(500);
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'error'=>'server_error']);
    } else { echo 'เกิดข้อผิดพลาด กรุณาลองใหม่'; }
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['success' => false, 'error' => 'csrf_failed']));
        }
        exit('คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
    }
}

function imageExtension(string $temporaryFile): string
{
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);
    $info = @getimagesize($temporaryFile);
    if (!isset($types[$type]) || !$info || ($info['mime'] ?? '') !== $type) {
        throw new InvalidArgumentException('Invalid image');
    }
    if ($info[0] * $info[1] > 40000000) {
        throw new InvalidArgumentException('Image dimensions too large');
    }
    return $types[$type];
}
