<?php
require_once __DIR__ . '/../../src/security.php';
// public/api/events.php
/* session initialized by security.php */
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

// Filter support for calendar.php refetchEvents approach
$searchText   = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$filterValue  = isset($_GET['filter']) ? $_GET['filter'] : 'all';

if ($searchText !== '' || $filterValue !== 'all') {
    $events = array_filter($events, function($e) use ($searchText, $filterValue) {
        if ($searchText !== '' && strpos(strtolower($e['title']), $searchText) === false) return false;
        if ($filterValue === 'registered'     && !$e['is_registered']) return false;
        if ($filterValue === 'not_registered' &&  $e['is_registered']) return false;
        return true;
    });
    $events = array_values($events);
}

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
        'attended'         => (bool) $e['is_attended'],
        'maxParticipants'  => $e['max_participants'],
        'registeredCount'  => (int) $e['registered_count'],
        'targetYears'      => $e['target_years'],
        'checkinCode'      => $isAdmin ? $e['checkin_code'] : null,
        'activityHours'    => (float) $e['activity_hours'],
        ],
    ];
}, $events);

echo json_encode($formatted);
