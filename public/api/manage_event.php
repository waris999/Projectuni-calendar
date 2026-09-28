<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Event.php';
require_once __DIR__ . '/../../src/Notification.php';
require_once __DIR__ . '/../../src/Mailer.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$eventModel = new Event($pdo);
$notifModel = new Notification($pdo);
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

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
        ? (int) $data['max_participants'] : null;

    $targetYears = null;
    if (!empty($data['target_years']) && is_array($data['target_years'])) {
        $targetYears = implode(',', $data['target_years']);
    }

    $checkinCode = trim($data['checkin_code'] ?? '');
    if ($checkinCode === '') {
        $checkinCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    $ok = $eventModel->create([
        'title'            => $title,
        'description'      => trim($data['description'] ?? ''),
        'location'         => trim($data['location'] ?? ''),
        'start_datetime'   => $start,
        'end_datetime'     => $end,
        'max_participants' => $maxParticipants,
        'target_years'     => $targetYears,
        'checkin_code'     => $checkinCode,
        'created_by'       => $_SESSION['user_id'],
    ]);

        if ($ok) {
        $notifModel->notifyStudents($targetYears, "มีกิจกรรมใหม่: {$title}", 'calendar.php');

        // ส่งอีเมลแจ้งเตือนด้วย (เฉพาะคนที่กรอกอีเมลไว้)
        $emailStmt = $pdo->prepare("SELECT fullname, email FROM users WHERE role = 'student' AND email IS NOT NULL AND email != ''");
        $emailStmt->execute();
        foreach ($emailStmt->fetchAll() as $u) {
            Mailer::send(
                $u['email'],
                $u['fullname'],
                "มีกิจกรรมใหม่: {$title}",
                "<p>สวัสดีครับ/ค่ะ {$u['fullname']}</p><p>มีกิจกรรมใหม่ \"{$title}\" ที่คุณอาจสนใจ ลงทะเบียนได้ที่ระบบ Uni Calendar</p>"
            );
        }
    }

    echo json_encode(['success' => $ok]);
    exit;
}

if ($action === 'delete') {
    $eventId = (int) ($data['event_id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_id']);
        exit;
    }

    // ต้องดึงข้อมูล+แจ้งเตือนก่อนลบ เพราะลบ event แล้ว registrations จะถูกลบตามไปด้วย (CASCADE)
    $event = $eventModel->findById($eventId);
    if ($event) {
        $notifModel->notifyRegisteredUsers($eventId, "กิจกรรม \"{$event['title']}\" ถูกยกเลิก", 'dashboard.php');
    }

    $ok = $eventModel->delete($eventId);
    echo json_encode(['success' => $ok]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'invalid_action']);