<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Event.php';
require_once __DIR__ . '/../src/Notification.php';

$isAdmin = $_SESSION['role'] === 'admin';

$eventModel = new Event($pdo);
$myEvents = $eventModel->getRegisteredByUser($_SESSION['user_id']);

$notifModel = new Notification($pdo);
$unreadCount = $isAdmin ? 0 : $notifModel->countUnread($_SESSION['user_id']);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>หน้าหลัก | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php">
            <img src="<?= $avatarSrc ?>" alt="avatar">
        </a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">
            <?php if ($isAdmin): ?>
                เจ้าหน้าที่
            <?php else: ?>
                นักศึกษา<?= !empty($_SESSION['student_year']) ? ' ชั้นปีที่ ' . (int) $_SESSION['student_year'] : '' ?>
            <?php endif; ?>
        </div>
    </div>
        <nav>
        <a href="dashboard.php" class="active">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
            <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <?php else: ?>
            <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
            <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <?php endif; ?>
        <a href="notifications.php">🔔 แจ้งเตือน<?= $unreadCount > 0 ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?></a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / หน้าหลัก</div>
    <div class="content">

        <div class="card">
            <h2>ข้อมูลของฉัน</h2>
            <p>ชื่อ: <?= htmlspecialchars($_SESSION['fullname']) ?></p>
            <p>รหัสนักศึกษา: <?= htmlspecialchars($_SESSION['username']) ?></p>
            <p>สถานะ:
                <?php if ($isAdmin): ?>
                    เจ้าหน้าที่
                <?php else: ?>
                    นักศึกษา<?= !empty($_SESSION['student_year']) ? ' ชั้นปีที่ ' . (int) $_SESSION['student_year'] : '' ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="card">
            <h2>กิจกรรมที่กำลังจะถึง</h2>
            <?php if (empty($myEvents)): ?>
                <p>ยังไม่มีกิจกรรมที่ลงทะเบียนไว้</p>
            <?php else: ?>
                <?php foreach ($myEvents as $ev): ?>
                    <div style="padding:12px 0; border-bottom:1px solid #eee;">
                        <strong><?= htmlspecialchars($ev['title']) ?></strong><br>
                        <span style="font-size:13px; color:#666;">
                            📍 <?= htmlspecialchars($ev['location']) ?> |
                            🕒 <?= date('d/m/Y H:i', strtotime($ev['start_datetime'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="calendar.php" class="btn" style="margin-top:14px; display:inline-block;">ไปหน้าปฏิทิน</a>
        </div>

    </div>
</div>

</body>
</html>