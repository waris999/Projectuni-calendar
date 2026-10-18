<?php
require_once __DIR__ . '/../../src/security.php';
/* session initialized by security.php */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Registration.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit(json_encode(['error'=>'method_not_allowed'])); }
if (($_SESSION['role'] ?? '') !== 'student') { http_response_code(403); exit(json_encode(['error'=>'forbidden'])); }
$data = json_decode(file_get_contents('php://input'), true);
$eventId = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$code = is_string($data['code'] ?? null) ? trim($data['code']) : '';

if ($eventId <= 0 || ($code === '' || strlen($code) > 10)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$regModel = new Registration($pdo);
$attempts = $_SESSION['checkin_attempts'][$eventId] ?? ['count'=>0, 'until'=>0];
if ($attempts['until'] <= time()) $attempts = ['count'=>0, 'until'=>time()+900];
if ($attempts['count'] >= 10) { http_response_code(429); exit(json_encode(['result'=>'rate_limited'])); }
try { $result = $regModel->checkin($_SESSION['user_id'], $eventId, $code); } catch (Throwable $e) { http_response_code(500); exit(json_encode(['result'=>'server_error'])); }
if ($result === 'wrong_code') $attempts['count']++;
if ($result === 'success') $attempts['count'] = 0;
$_SESSION['checkin_attempts'][$eventId] = $attempts;

echo json_encode(['result' => $result]);
