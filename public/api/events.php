<?php
// public/api/events.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Event.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$eventModel = new Event($pdo);
$events = $eventModel->getAllForUser($_SESSION['user_id']);
$isAdmin = $_SESSION['role'] === 'admin';

$formatted = array_map(function ($e) use ($isAdmin) {
    return [
        'id'    => $e['id'],
        'title' => $e['title'],
        'start' => $e['start_datetime'],
        'end'   => $e['end_datetime'],
        'extendedProps' => [
        'description'      => $e['description'],
        'location'         => $e['location'],
        'registered'       => (bool) $e['is_registered'],
        'maxParticipants'  => $e['max_participants'],
        'registeredCount'  => (int) $e['registered_count'],
        'targetYears'      => $e['target_years'],
        'checkinCode'      => $isAdmin ? $e['checkin_code'] : null,
        'activityHours'    => (float) $e['activity_hours'],
        ],
    ];
}, $events);

echo json_encode($formatted);