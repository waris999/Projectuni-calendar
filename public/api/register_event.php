<?php
require_once __DIR__ . '/../../src/security.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit(json_encode(['error'=>'method_not_allowed'])); }
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') { http_response_code(403); exit(json_encode(['error'=>'forbidden'])); }
require_once __DIR__ . '/../../src/Registration.php';
$data = json_decode(file_get_contents('php://input'), true);
$eventId = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$action = $data['action'] ?? '';
if (!$eventId || !in_array($action, ['register','cancel'], true)) { http_response_code(400); exit(json_encode(['error'=>'invalid_request'])); }
try {
    $model = new Registration($pdo);
    $result = $model->$action((int)$_SESSION['user_id'], $eventId);
    echo json_encode(['success'=>$result === true, 'registered'=>$result === true && $action === 'register', 'error'=>is_string($result) ? $result : null]);
} catch (Throwable $e) {
    error_log('Registration operation failed');
    http_response_code(500); echo json_encode(['success'=>false,'error'=>'server_error']);
}
