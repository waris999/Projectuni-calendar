<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Notification.php';

$isAdmin = $_SESSION['role'] === 'admin';
$notifModel = new Notification($pdo);
$items = $notifModel->getForUser($_SESSION['user_id']);
$notifModel->markAllRead($_SESSION['user_id']);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แจ้งเตือน | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<style>
    .notif-item { display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid #eee; }
    .notif-item:last-child { border-bottom: none; }
    .notif-item .dot { width: 8px; height: 8px; border-radius: 50%; background: #1e5631; margin-top: 6px; flex-shrink: 0; }
    .notif-item.read .dot { background: #ccc; }
    .notif-item .msg { font-size: 14px; }
    .notif-item .time { font-size: 12px; color: #999; margin-top: 2px; }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="avatar"></a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">
            <?php if ($isAdmin): ?>เจ้าหน้าที่<?php else: ?>
                นักศึกษา<?= !empty($_SESSION['student_year']) ? ' ชั้นปีที่ ' . (int) $_SESSION['student_year'] : '' ?>
            <?php endif; ?>
        </div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
            <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <?php else: ?>
            <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
            <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <?php endif; ?>
        <a href="notifications.php" class="active">🔔 แจ้งเตือน</a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / แจ้งเตือน</div>
    <div class="content">
        <div class="card">
            <h2>การแจ้งเตือนทั้งหมด</h2>
            <?php if (empty($items)): ?>
                <p>ยังไม่มีการแจ้งเตือน</p>
            <?php else: ?>
                <?php foreach ($items as $n): ?>
                    <div class="notif-item <?= $n['is_read'] ? 'read' : '' ?>">
                        <div class="dot"></div>
                        <div>
                            <div class="msg"><?= htmlspecialchars($n['message']) ?></div>
                            <div class="time"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>