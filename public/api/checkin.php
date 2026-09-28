<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Registration.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$eventId = (int) ($data['event_id'] ?? 0);
$code = trim($data['code'] ?? '');

if ($eventId <= 0 || $code === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$regModel = new Registration($pdo);
$result = $regModel->checkin($_SESSION['user_id'], $eventId, $code);

echo json_encode(['result' => $result]);