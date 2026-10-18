<?php
require_once __DIR__ . '/../../src/security.php';
/* session initialized by security.php */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Event.php';
require_once __DIR__ . '/../../src/Notification.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$eventModel = new Event($pdo);
$notifModel = new Notification($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit(json_encode(['error'=>'method_not_allowed'])); }
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
if (in_array($action, ['create','edit'], true)) {
    $valid = is_array($data);
    foreach (['title','description','location','start_datetime','end_datetime','checkin_code'] as $field) {
        if (isset($data[$field]) && !is_string($data[$field])) $valid = false;
    }
    if (!$valid) { http_response_code(400); exit(json_encode(['error'=>'invalid_fields'])); }
    $startDate = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['start_datetime'] ?? '');
    $endDate = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['end_datetime'] ?? '');
    $max = $data['max_participants'] ?? '';
    $hours = $data['activity_hours'] ?? 0;
    $years = $data['target_years'] ?? [];
    $code = trim($data['checkin_code'] ?? '');
    $length = static fn(string $s): int => preg_match_all('/./us', $s);
    if (!$startDate || !$endDate || $startDate->format('Y-m-d H:i:s') !== ($data['start_datetime'] ?? '') || $endDate->format('Y-m-d H:i:s') !== ($data['end_datetime'] ?? '') || $endDate <= $startDate
        || $length(trim($data['title'] ?? '')) < 1 || $length($data['title']) > 150 || $length($data['location'] ?? '') > 150
        || ($max !== '' && $max !== null && filter_var($max, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2147483647]]) === false)
        || !is_numeric($hours) || !is_finite((float)$hours) || (float)$hours < 0 || (float)$hours > 999.9
        || !is_array($years) || count($years) > 8 || array_filter($years, static fn($v) => !is_scalar($v) || !preg_match('/^[1-8]$/D', (string)$v))
        || ($code !== '' && !preg_match('/^[A-Za-z0-9]{1,10}$/D', $code))) {
        http_response_code(400); exit(json_encode(['error'=>'invalid_fields']));
    }
}


// ---- GET: ดึงข้อมูล event เดิมมาเติมฟอร์มแก้ไข ----
if ($action === 'get') {
    $eventId = (int)($data['event_id'] ?? 0);
    $event = $eventModel->findById($eventId);
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    echo json_encode(['success' => true, 'event' => $event]);
    exit;
}

// ---- CREATE ----
if ($action === 'create') {
    $title = trim($data['title'] ?? '');
    $start = trim($data['start_datetime'] ?? '');
    $end   = trim($data['end_datetime'] ?? '');

    if ($title === '' || $start === '' || $end === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing_fields']);
        exit;
    }

    $maxParticipants = isset($data['max_participants']) && $data['max_participants'] !== ''
        ? (int)$data['max_participants'] : null;

    $targetYears = null;
    if (!empty($data['target_years']) && is_array($data['target_years'])) {
        $targetYears = implode(',', $data['target_years']);
    }

    $checkinCode = trim($data['checkin_code'] ?? '');
    if ($checkinCode === '') {
        $checkinCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    $activityHours = isset($data['activity_hours']) && $data['activity_hours'] !== ''
        ? (float)$data['activity_hours'] : 0;

    $ok = $eventModel->create([
        'title'            => $title,
        'description'      => trim($data['description'] ?? ''),
        'location'         => trim($data['location'] ?? ''),
        'start_datetime'   => $start,
        'end_datetime'     => $end,
        'max_participants' => $maxParticipants,
        'target_years'     => $targetYears,
        'checkin_code'     => $checkinCode,
        'activity_hours'   => $activityHours,
        'created_by'       => $_SESSION['user_id'],
    ]);

    if ($ok) {
        $notifModel->notifyStudents($targetYears, "มีกิจกรรมใหม่: {$title}", 'calendar.php');
    }

    echo json_encode(['success' => $ok]);
    exit;
}

// ---- EDIT ----
if ($action === 'edit') {
    $eventId = (int)($data['event_id'] ?? 0);
    $title   = trim($data['title'] ?? '');
    $start   = trim($data['start_datetime'] ?? '');
    $end     = trim($data['end_datetime'] ?? '');

    if ($eventId <= 0 || $title === '' || $start === '' || $end === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing_fields']);
        exit;
    }

    $maxParticipants = isset($data['max_participants']) && $data['max_participants'] !== ''
        ? (int)$data['max_participants'] : null;

    $targetYears = null;
    if (!empty($data['target_years']) && is_array($data['target_years'])) {
        $targetYears = implode(',', $data['target_years']);
    }

    $activityHours = isset($data['activity_hours']) && $data['activity_hours'] !== ''
        ? (float)$data['activity_hours'] : 0;

    $ok = $eventModel->update($eventId, [
        'title'            => $title,
        'description'      => trim($data['description'] ?? ''),
        'location'         => trim($data['location'] ?? ''),
        'start_datetime'   => $start,
        'end_datetime'     => $end,
        'max_participants' => $maxParticipants,
        'target_years'     => $targetYears,
        'checkin_code'     => trim($data['checkin_code'] ?? ''),
        'activity_hours'   => $activityHours,
    ]);

    // แจ้งเตือนคนที่ลงทะเบียนว่ากิจกรรมมีการเปลี่ยนแปลง
    if ($ok) {
        $notifModel->notifyRegisteredUsers(
            $eventId,
            "กิจกรรม \"{$title}\" มีการเปลี่ยนแปลงรายละเอียด กรุณาตรวจสอบอีกครั้ง",
            'calendar.php'
        );
    }

    echo json_encode(['success' => $ok]);
    exit;
}

// ---- DELETE ----
if ($action === 'delete') {
    $eventId = (int)($data['event_id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_id']);
        exit;
    }

    $event = $eventModel->findById($eventId);
    if ($event) {
        $notifModel->notifyRegisteredUsers(
            $eventId,
            "กิจกรรม \"{$event['title']}\" ถูกยกเลิก",
            'dashboard.php'
        );
    }

    $ok = $eventModel->delete($eventId);
    echo json_encode(['success' => $ok]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'invalid_action']);
