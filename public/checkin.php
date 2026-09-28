<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if ($_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Event.php';
require_once __DIR__ . '/../src/Notification.php';

$eventModel = new Event($pdo);
$myEvents = $eventModel->getRegisteredByUserForCheckin($_SESSION['user_id']);

$autoEventId = (int) ($_GET['auto_event'] ?? 0);
$autoCode = trim($_GET['auto_code'] ?? '');

$notifModel = new Notification($pdo);
$unreadCount = $notifModel->countUnread($_SESSION['user_id']);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>เช็คอินกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<style>
    .checkin-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid #eee;
        gap: 12px;
        flex-wrap: wrap;
    }
    .checkin-item:last-child { border-bottom: none; }
    .checkin-item .info strong { display:block; }
    .checkin-item .info span { font-size: 12px; color: #666; }
    .badge {
        font-size: 12px;
        padding: 3px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }
    .badge-attended { background: #e6f4ea; color: #1e5631; }
    .badge-pending { background: #fff4e5; color: #b8860b; }
    .checkin-form { display: flex; gap: 8px; }
    .checkin-form input {
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 13px;
        width: 120px;
    }
    .checkin-form button {
        background: #1e5631;
        color: #fff;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
    }
    .checkin-msg { font-size: 12px; margin-top: 4px; width: 100%; }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php">
            <img src="<?= $avatarSrc ?>" alt="avatar">
        </a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">
            นักศึกษา<?= !empty($_SESSION['student_year']) ? ' ชั้นปีที่ ' . (int) $_SESSION['student_year'] : '' ?>
        </div>
    </div>
        <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="checkin.php" class="active">✅ เช็คอินกิจกรรม</a>
        <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <a href="notifications.php">🔔 แจ้งเตือน<?= $unreadCount > 0 ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?></a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / เช็คอินกิจกรรม</div>
    <div class="content">

        <div class="card">
            <h2>ยืนยันการเข้าร่วมกิจกรรม</h2>
            <p style="font-size:13px; color:#666; margin-bottom:14px;">
                กรอกรหัสเช็คอินที่ได้รับหน้างาน หรือสแกน QR Code เพื่อยืนยันอัตโนมัติ
            </p>
            <button class="btn-outline" id="openScannerBtn" style="width:200px; margin-bottom:16px;">📷 สแกน QR Code</button>
            <div id="qrScannerBox" style="display:none; max-width:320px; margin-bottom:16px;"></div>

            <?php if (empty($myEvents)): ?>
                <p>คุณยังไม่ได้ลงทะเบียนกิจกรรมใดๆ</p>
            <?php else: ?>
                <?php foreach ($myEvents as $ev): ?>
                    <?php
                        $isAttended = $ev['status'] === 'attended';
                        $hasStarted = strtotime($ev['start_datetime']) <= time();
                    ?>
                    <div class="checkin-item">
                        <div class="info">
                            <strong><?= htmlspecialchars($ev['title']) ?></strong>
                            <span>📍 <?= htmlspecialchars($ev['location']) ?> | 🕒 <?= date('d/m/Y H:i', strtotime($ev['start_datetime'])) ?></span>
                        </div>

                        <?php if ($isAttended): ?>
                            <span class="badge badge-attended">เช็คอินแล้ว ✓</span>
                        <?php elseif (!$hasStarted): ?>
                            <span class="badge badge-pending">ยังไม่ถึงเวลากิจกรรม</span>
                        <?php else: ?>
                            <div class="checkin-form">
                                <input type="text" placeholder="กรอกรหัส" class="checkin-code-input" maxlength="10">
                                <button onclick="submitCheckin(<?= $ev['id'] ?>, this)">ยืนยัน</button>
                            </div>
                            <div class="checkin-msg"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
async function submitCheckin(eventId, btnEl) {
    const wrap = btnEl.closest('.checkin-item');
    const codeInput = wrap.querySelector('.checkin-code-input');
    const msgEl = wrap.querySelector('.checkin-msg');
    const code = codeInput.value.trim();

    if (!code) {
        msgEl.textContent = 'กรุณากรอกรหัส';
        msgEl.style.color = '#b71c1c';
        return;
    }

    msgEl.textContent = 'กำลังตรวจสอบ...';
    msgEl.style.color = '#666';

    try {
        const res = await fetch('api/checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: eventId, code: code })
        });
        const result = await res.json();

        if (result.result === 'success') {
            msgEl.textContent = 'เช็คอินสำเร็จ!';
            msgEl.style.color = '#1e5631';
            setTimeout(() => location.reload(), 800);
        } else if (result.result === 'wrong_code') {
            msgEl.textContent = 'รหัสไม่ถูกต้อง';
            msgEl.style.color = '#b71c1c';
        } else if (result.result === 'not_started') {
            msgEl.textContent = 'กิจกรรมยังไม่เริ่ม';
            msgEl.style.color = '#b71c1c';
        } else if (result.result === 'already_checked_in') {
            msgEl.textContent = 'คุณเช็คอินไปแล้ว';
            msgEl.style.color = '#1e5631';
        } else {
            msgEl.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            msgEl.style.color = '#b71c1c';
        }
    } catch (err) {
        msgEl.textContent = 'เชื่อมต่อไม่สำเร็จ';
        msgEl.style.color = '#b71c1c';
    }
}
document.getElementById('openScannerBtn').addEventListener('click', function () {
    const box = document.getElementById('qrScannerBox');
    if (box.style.display === 'none') {
        box.style.display = 'block';
        box.innerHTML = '<div id="qrReader" style="width:100%;"></div>';
        const scanner = new Html5Qrcode('qrReader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: 200 },
            (decodedText) => {
                scanner.stop();
                box.style.display = 'none';
                window.location.href = decodedText;
            }
        ).catch(err => {
            box.innerHTML = '<p style="color:#b71c1c; font-size:13px;">เปิดกล้องไม่สำเร็จ กรุณาอนุญาตการใช้กล้องหรือกรอกรหัสเอง</p>';
        });
    } else {
        box.style.display = 'none';
        box.innerHTML = '';
    }
});

<?php if ($autoEventId > 0 && $autoCode !== ''): ?>
// ถ้ามาจาก QR code สแกนแล้ว auto-กรอกและส่งให้อัตโนมัติ
window.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector(`button[onclick="submitCheckin(<?= $autoEventId ?>, this)"]`);
    if (btn) {
        const input = btn.closest('.checkin-item').querySelector('.checkin-code-input');
        if (input) {
            input.value = <?= json_encode($autoCode) ?>;
            submitCheckin(<?= $autoEventId ?>, btn);
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>