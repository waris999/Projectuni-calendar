<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Event.php';

$eventId = (int) ($_GET['event_id'] ?? 0);
if ($eventId <= 0) {
    die('ไม่พบกิจกรรมที่ต้องการ');
}

$eventModel = new Event($pdo);
$event = $eventModel->findById($eventId);
if (!$event) {
    die('ไม่พบกิจกรรมนี้ในระบบ');
}

$attendees = $eventModel->getAttendeesList($eventId);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายชื่อผู้เข้าร่วม - <?= htmlspecialchars($event['title']) ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Sarabun', 'Segoe UI', sans-serif;
        padding: 30px;
        color: #222;
    }
    .print-toolbar {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-bottom: 20px;
    }
    .print-toolbar button {
        background: #1e5631;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .print-toolbar button.secondary {
        background: #fff;
        color: #1e5631;
        border: 1px solid #1e5631;
    }
    .doc-header {
        text-align: center;
        margin-bottom: 24px;
        border-bottom: 2px solid #1e5631;
        padding-bottom: 16px;
    }
    .doc-header h1 {
        font-size: 20px;
        color: #1e5631;
        margin-bottom: 6px;
    }
    .doc-header .event-meta {
        font-size: 13px;
        color: #555;
        line-height: 1.6;
    }
    .doc-summary {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        margin-bottom: 14px;
        color: #444;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    th, td {
        border: 1px solid #999;
        padding: 8px 10px;
        text-align: left;
    }
    th {
        background: #e6f0e9;
        color: #1e5631;
        font-weight: 600;
    }
    .col-no { width: 45px; text-align: center; }
    .col-year { width: 60px; text-align: center; }
    .col-status { width: 90px; text-align: center; }
    .col-sign { width: 130px; }
    .status-attended { color: #1e5631; font-weight: 600; }
    .status-registered { color: #b8860b; }
    .doc-footer {
        margin-top: 30px;
        font-size: 12px;
        color: #777;
        text-align: right;
    }

    @media print {
        .print-toolbar { display: none; }
        body { padding: 10px; }
    }
</style>
</head>
<body>

<div class="print-toolbar">
    <button class="secondary" onclick="window.close()">ปิดหน้าต่าง</button>
    <button onclick="window.print()">🖨️ พิมพ์เอกสาร</button>
</div>

<div class="doc-header">
    <h1>รายชื่อผู้เข้าร่วมกิจกรรม</h1>
    <div class="event-meta">
        <strong><?= htmlspecialchars($event['title']) ?></strong><br>
        📍 <?= htmlspecialchars($event['location']) ?> &nbsp;|&nbsp;
        🕒 <?= date('d/m/Y H:i', strtotime($event['start_datetime'])) ?> - <?= date('H:i', strtotime($event['end_datetime'])) ?> น.
    </div>
</div>

<div class="doc-summary">
    <span>จำนวนผู้ลงทะเบียนทั้งหมด: <strong><?= count($attendees) ?></strong> คน</span>
    <span>วันที่พิมพ์เอกสาร: <?= date('d/m/Y H:i') ?> น.</span>
</div>

<table>
    <thead>
        <tr>
            <th class="col-no">ลำดับ</th>
            <th>รหัสนักศึกษา</th>
            <th>ชื่อ-นามสกุล</th>
            <th class="col-year">ชั้นปี</th>
            <th class="col-status">สถานะ</th>
            <th class="col-sign">ลายเซ็น</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($attendees)): ?>
            <tr>
                <td colspan="6" style="text-align:center; padding:20px; color:#999;">ยังไม่มีผู้ลงทะเบียนกิจกรรมนี้</td>
            </tr>
        <?php else: ?>
            <?php foreach ($attendees as $i => $a): ?>
                <tr>
                    <td class="col-no"><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($a['username']) ?></td>
                    <td><?= htmlspecialchars($a['fullname']) ?></td>
                    <td class="col-year"><?= $a['student_year'] ? (int) $a['student_year'] : '-' ?></td>
                    <td class="col-status">
                        <?php if ($a['status'] === 'attended'): ?>
                            <span class="status-attended">เช็คอินแล้ว</span>
                        <?php else: ?>
                            <span class="status-registered">ลงทะเบียน</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-sign">&nbsp;</td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="doc-footer">
    ระบบปฏิทินกิจกรรมมหาวิทยาลัย (Uni Calendar)
</div>

</body>
</html>