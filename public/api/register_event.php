<?php
// public/api/register_event.php
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
$action  = $data['action'] ?? '';

if ($eventId <= 0 || !in_array($action, ['register', 'cancel'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$regModel = new Registration($pdo);
$userId = $_SESSION['user_id'];

if ($action === 'register') {
    $result = $regModel->register($userId, $eventId);

    if ($result === 'full') {
        echo json_encode(['success' => false, 'error' => 'full']);
        exit;
    }
    if ($result === 'year_not_allowed') {
        echo json_encode(['success' => false, 'error' => 'year_not_allowed']);
        exit;
    }
    $ok = $result;
} else {
    $ok = $regModel->cancel($userId, $eventId);
}

echo json_encode(['success' => $ok, 'registered' => $action === 'register']);