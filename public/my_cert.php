<?php
require_once __DIR__ . '/../src/security.php';
/* session initialized by security.php */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Event.php';

$userId  = (int)$_SESSION['user_id'];
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$userStmt = $pdo->prepare("SELECT fullname, username, student_year FROM users WHERE id = :id LIMIT 1");
$userStmt->execute(['id' => $userId]);
$student = $userStmt->fetch();
if (!$student) { die('ไม่พบข้อมูลนักศึกษา'); }

if ($eventId > 0) {
    // ── โหมดใบเกียรติบัตรต่อกิจกรรม ────────────────────────────
    $chkStmt = $pdo->prepare(
        "SELECT e.title, e.start_datetime, e.end_datetime, e.location, e.activity_hours
         FROM registrations r
         INNER JOIN events e ON e.id = r.event_id
         WHERE r.user_id = :uid AND r.event_id = :eid AND r.status = 'attended'
         LIMIT 1"
    );
    $chkStmt->execute(['uid' => $userId, 'eid' => $eventId]);
    $singleEvent = $chkStmt->fetch();
    if (!$singleEvent) { die('ไม่พบข้อมูลการเข้าร่วมกิจกรรมนี้'); }
    $certMode = 'single';
} else {
    // ── โหมดใบรวมรายปี ──────────────────────────────────────────
    $certMode   = 'yearly';
    $currentAY  = currentAcademicYear();
    $selectedAY = requestedAcademicYear();
    $eventModel = new Event($pdo);
    $totalHours = $eventModel->getTotalHours($userId, $selectedAY);
    $evStmt = $pdo->prepare(
        "SELECT e.title, e.start_datetime, e.activity_hours
         FROM registrations r
         INNER JOIN events e ON e.id = r.event_id
         WHERE r.user_id = :uid AND r.status = 'attended'
           AND e.start_datetime >= :ay_start AND e.start_datetime < :ay_end
         ORDER BY e.start_datetime ASC"
    );
    [$ayStart, $ayEnd] = academicYearRange($selectedAY);
    $evStmt->execute(['uid' => $userId, 'ay_start' => $ayStart, 'ay_end' => $ayEnd]);
    $activities = $evStmt->fetchAll();
    $goalStmt = $pdo->prepare("SELECT required_hours FROM hour_goals WHERE academic_year = :ay AND student_year = :year LIMIT 1");
    $goalStmt->execute(['ay' => $selectedAY, 'year' => $student['student_year']]);
    $goalRow       = $goalStmt->fetch();
    $requiredHours = $goalRow ? (float)$goalRow['required_hours'] : 0;
    $isComplete    = $requiredHours > 0 && $totalHours >= $requiredHours;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title><?= $certMode === 'single' ? htmlspecialchars($singleEvent['title']) : 'ใบรับรองกิจกรรม' ?> | <?= htmlspecialchars($student['fullname']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap');
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Sarabun',sans-serif; background:#f0f4f0; display:flex; flex-direction:column; align-items:center; padding:30px 20px; }
.print-btn-bar { margin-bottom:20px; display:flex; gap:10px; }
.print-btn-bar button { padding:10px 24px; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-family:'Sarabun',sans-serif; }
.btn-print { background:#1e5631; color:#fff; }
.btn-back  { background:#fff; color:#1e5631; border:1px solid #1e5631; }
.cert { width:794px; background:#fff; border:3px solid #1e5631; border-radius:4px; padding:50px 60px; position:relative; box-shadow:0 4px 20px rgba(0,0,0,0.12); }
.cert::before,.cert::after { content:''; position:absolute; width:40px; height:40px; border:3px solid #4caf7d; }
.cert::before { top:8px; left:8px; border-right:none; border-bottom:none; }
.cert::after  { bottom:8px; right:8px; border-left:none; border-top:none; }
.cert-header { text-align:center; border-bottom:2px solid #e6f0e9; padding-bottom:24px; margin-bottom:28px; }
.cert-logo { width:64px; height:64px; background:#1e5631; border-radius:50%; margin:0 auto 12px; display:flex; align-items:center; justify-content:center; font-size:28px; }
.cert-header h1 { font-size:24px; color:#1e5631; font-weight:700; letter-spacing:2px; margin-bottom:4px; }
.cert-header p  { font-size:14px; color:#666; }
.cert-body { text-align:center; margin-bottom:32px; }
.cert-body .given-to     { font-size:15px; color:#555; margin-bottom:8px; }
.cert-body .student-name { font-size:32px; font-weight:700; color:#1a4a2e; margin-bottom:6px; border-bottom:2px dotted #4caf7d; display:inline-block; padding-bottom:4px; }
.cert-body .student-id   { font-size:14px; color:#777; margin-bottom:16px; }
.cert-body .desc         { font-size:15px; color:#444; line-height:1.8; max-width:500px; margin:0 auto 20px; }
.cert-body .hours-badge  { display:inline-block; background:#1e5631; color:#fff; font-size:22px; font-weight:700; padding:12px 36px; border-radius:40px; margin-bottom:8px; }
.cert-body .hours-label  { font-size:13px; color:#888; }
.status-badge            { display:inline-block; margin:16px auto 0; padding:6px 20px; border-radius:20px; font-size:14px; font-weight:600; }
.status-complete         { background:#e6f4ea; color:#1e5631; border:1px solid #4caf7d; }
.status-incomplete       { background:#fdecea; color:#b71c1c; border:1px solid #f08080; }
.activity-table          { width:100%; border-collapse:collapse; margin-top:24px; font-size:13px; }
.activity-table th       { background:#1e5631; color:#fff; padding:8px 12px; text-align:left; }
.activity-table td       { padding:7px 12px; border-bottom:1px solid #eee; color:#444; }
.activity-table tr:last-child td { border-bottom:none; }
.activity-table tr:nth-child(even) td { background:#f8fdf9; }
.cert-footer { margin-top:40px; display:flex; justify-content:space-between; align-items:flex-end; border-top:1px solid #eee; padding-top:20px; }
.cert-footer .sign-block { text-align:center; width:180px; }
.cert-footer .sign-line  { border-top:1px solid #333; margin-bottom:6px; }
.cert-footer .sign-block p { font-size:12px; color:#555; }
.cert-footer .issued-info  { font-size:12px; color:#888; text-align:right; }
@media print { body { background:#fff; padding:0; } .print-btn-bar { display:none; } .cert { box-shadow:none; border-radius:0; width:100%; } }
</style>
<link rel="stylesheet" href="css/certificate-print.css?v=<?= filemtime(__DIR__ . '/css/certificate-print.css') ?>">
</head>
<body>

<div class="print-btn-bar">
    <button class="btn-print" onclick="document.fonts.ready.then(() => window.print())">🖨️ พิมพ์ / บันทึก PDF</button>
    <button class="btn-back" onclick="history.back()">← กลับ</button>
</div>

<div class="cert">
    <div class="cert-header">
        <div class="cert-logo">🎓</div>
        <h1>ใบรับรองการเข้าร่วมกิจกรรม</h1>
        <p>มหาวิทยาลัยราชภัฏพระนคร</p>
    </div>

    <div class="cert-body">
        <p class="given-to">ขอมอบใบรับรองนี้แก่</p>
        <div class="student-name"><?= htmlspecialchars($student['fullname']) ?></div>
        <div class="student-id">รหัสนักศึกษา <?= htmlspecialchars($student['username']) ?> | นักศึกษาชั้นปีที่ <?= (int)$student['student_year'] ?></div>

        <?php if ($certMode === 'single'): ?>
            <p class="desc">เพื่อรับรองว่านักศึกษาได้เข้าร่วมกิจกรรม</p>
            <div style="font-size:20px; font-weight:700; color:#1a4a2e; margin:10px 0;">
                <?= htmlspecialchars($singleEvent['title']) ?>
            </div>
            <div style="font-size:13px; color:#777; margin-bottom:16px;">
                📍 <?= htmlspecialchars($singleEvent['location'] ?? '') ?> &nbsp;|&nbsp;
                🕒 <?= date('d/m/Y', strtotime($singleEvent['start_datetime'])) ?>
            </div>
            <div class="hours-badge"><?= number_format((float)$singleEvent['activity_hours'], 1) ?> ชั่วโมง</div>
            <div class="hours-label">ชั่วโมงกิจกรรม</div>
            <div style="margin-top:16px;">
                <span class="status-badge status-complete">✅ เข้าร่วมกิจกรรมครบถ้วน</span>
            </div>
        <?php else: ?>
            <p class="desc">เพื่อรับรองว่านักศึกษาได้เข้าร่วมกิจกรรมเสริมหลักสูตร<br>ในปีการศึกษา <?= htmlspecialchars($selectedAY) ?> รวมทั้งสิ้น</p>
            <div class="hours-badge"><?= number_format($totalHours, 1) ?> ชั่วโมง</div>
            <div class="hours-label">
                <?php if ($requiredHours > 0): ?>จากเป้าหมาย <?= number_format($requiredHours, 1) ?> ชั่วโมง<?php endif; ?>
            </div>
            <?php if ($requiredHours > 0): ?>
            <div>
                <span class="status-badge <?= $isComplete ? 'status-complete' : 'status-incomplete' ?>">
                    <?= $isComplete ? '✅ ผ่านเกณฑ์กิจกรรมครบถ้วน' : '⚠️ ยังไม่ครบเกณฑ์' ?>
                </span>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($certMode === 'yearly' && !empty($activities)): ?>
    <table class="activity-table">
        <thead>
            <tr><th>#</th><th>ชื่อกิจกรรม</th><th>วันที่</th><th style="text-align:center;">ชั่วโมง</th></tr>
        </thead>
        <tbody>
            <?php foreach ($activities as $i => $act): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($act['title']) ?></td>
                <td><?= date('d/m/Y', strtotime($act['start_datetime'])) ?></td>
                <td style="text-align:center;"><?= number_format((float)$act['activity_hours'], 1) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="cert-footer">
        <div class="sign-block">
            <div class="sign-line"></div>
            <p>ผู้รับรอง</p>
            <p style="color:#888;">(มหาวิทยาลัยราชภัฏพระนคร)</p>
        </div>
        <div class="issued-info">
            <p>ออกเอกสารเมื่อ <?= date('d/m/Y') ?></p>
            <p style="margin-top:4px; font-size:11px; color:#aaa;">เอกสารนี้ออกโดยระบบ Uni Calendar</p>
        </div>
        <div class="sign-block">
            <div class="sign-line"></div>
            <p>นักศึกษา</p>
            <p style="color:#888;">(<?= htmlspecialchars($student['fullname']) ?>)</p>
        </div>
    </div>
</div>

</body>
</html>
