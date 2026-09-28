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

$eventModel = new Event($pdo);
$history = $eventModel->getHistoryForUser($_SESSION['user_id']);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ประวัติกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<style>
    .history-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #eee; gap: 12px; flex-wrap: wrap; }
    .history-item:last-child { border-bottom: none; }
    .badge { font-size: 12px; padding: 3px 10px; border-radius: 12px; white-space: nowrap; }
    .badge-attended { background: #e6f4ea; color: #1e5631; }
    .badge-registered { background: #fff4e5; color: #b8860b; }
    .badge-cancelled { background: #f4f4f4; color: #999; }
    .badge-missed { background: #fdecea; color: #b71c1c; }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="avatar"></a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">นักศึกษา<?= !empty($_SESSION['student_year']) ? ' ชั้นปีที่ ' . (int) $_SESSION['student_year'] : '' ?></div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
        <a href="history.php" class="active">🕘 ประวัติกิจกรรม</a>
        <a href="notifications.php">🔔 แจ้งเตือน</a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / ประวัติกิจกรรม</div>
    <div class="content">
        <div class="card">
            <h2>ประวัติการเข้าร่วมกิจกรรมทั้งหมด</h2>
            <?php if (empty($history)): ?>
                <p>ยังไม่มีประวัติกิจกรรม</p>
            <?php else: ?>
                <?php foreach ($history as $ev):
                    $isPast = strtotime($ev['end_datetime']) < time();
                    if ($ev['reg_status'] === 'attended') {
                        $badgeClass = 'badge-attended'; $badgeText = 'เข้าร่วมแล้ว ✓';
                    } elseif ($ev['reg_status'] === 'cancelled') {
                        $badgeClass = 'badge-cancelled'; $badgeText = 'ยกเลิกแล้ว';
                    } elseif ($isPast) {
                        $badgeClass = 'badge-missed'; $badgeText = 'ไม่ได้เช็คอิน';
                    } else {
                        $badgeClass = 'badge-registered'; $badgeText = 'รอเข้าร่วม';
                    }
                ?>
                    <div class="history-item">
                        <div>
                            <strong><?= htmlspecialchars($ev['title']) ?></strong><br>
                            <span style="font-size:13px; color:#666;">
                                📍 <?= htmlspecialchars($ev['location']) ?> |
                                🕒 <?= date('d/m/Y H:i', strtotime($ev['start_datetime'])) ?>
                            </span>
                        </div>
                        <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>