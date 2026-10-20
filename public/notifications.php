<?php
require_once __DIR__ . '/../src/security.php';
/* session initialized by security.php */
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Notification.php';

$isAdmin = $_SESSION['role'] === 'admin';
$notifModel = new Notification($pdo);
$items = $notifModel->getForUser($_SESSION['user_id']);
$unreadCount = $notifModel->countUnread($_SESSION['user_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifModel->markAllRead($_SESSION['user_id']);
    header('Location: notifications.php'); exit;
}

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">
<title>แจ้งเตือน | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
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
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="รูปโปรไฟล์" width="70" height="70" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';"></a>
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
            <a href="admin_hours.php">⏱️ ชั่วโมงกิจกรรม</a>
            <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <?php else: ?>
            <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
            <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <?php endif; ?>
        <a href="notifications.php" class="active">🔔 แจ้งเตือน</a>
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / แจ้งเตือน</div>
    <div class="content">
<div class="card">
            <div class="notifications-heading">
                <h2>การแจ้งเตือนทั้งหมด</h2>
                <form method="POST" class="notifications-actions">
                    <?= csrfField() ?>
                    <button type="submit" class="btn notifications-read-button" <?= $unreadCount === 0 ? 'disabled' : '' ?>>
                        <span aria-hidden="true">✓</span> ทำเครื่องหมายว่าอ่านทั้งหมดแล้ว
                    </button>
                </form>
            </div>
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

<script src="js/mobile-layout.js?v=<?= filemtime(__DIR__ . '/js/mobile-layout.js') ?>"></script>
</body>
</html>
