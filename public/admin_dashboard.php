<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';

$userModel = new User($pdo);
$userStats = $userModel->getStats();

$totalEvents = (int) $pdo->query("SELECT COUNT(*) c FROM events")->fetch()['c'];
$upcomingEvents = (int) $pdo->query("SELECT COUNT(*) c FROM events WHERE start_datetime >= NOW()")->fetch()['c'];
$totalRegistrations = (int) $pdo->query("SELECT COUNT(*) c FROM registrations WHERE status IN ('registered','attended')")->fetch()['c'];
$totalAttended = (int) $pdo->query("SELECT COUNT(*) c FROM registrations WHERE status = 'attended'")->fetch()['c'];

$upcomingList = $pdo->query(
    "SELECT title, start_datetime, max_participants,
     (SELECT COUNT(*) FROM registrations r WHERE r.event_id = events.id AND r.status IN ('registered','attended')) AS reg_count
     FROM events WHERE start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT 5"
)->fetchAll();

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>แดชบอร์ด | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="avatar"></a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">เจ้าหน้าที่</div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="admin_dashboard.php" class="active">📊 แดชบอร์ด</a>
        <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <a href="notifications.php">🔔 แจ้งเตือน</a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / แดชบอร์ด</div>
    <div class="content">

        <div class="stat-grid">
            <div class="stat-card"><div class="num"><?= $userStats['total_students'] ?></div><div class="label">นักศึกษาทั้งหมด</div></div>
            <div class="stat-card"><div class="num"><?= $totalEvents ?></div><div class="label">กิจกรรมทั้งหมด</div></div>
            <div class="stat-card"><div class="num"><?= $upcomingEvents ?></div><div class="label">กิจกรรมที่กำลังจะถึง</div></div>
            <div class="stat-card"><div class="num"><?= $totalRegistrations ?></div><div class="label">ยอดลงทะเบียนรวม</div></div>
            <div class="stat-card"><div class="num"><?= $totalAttended ?></div><div class="label">เช็คอินสำเร็จ</div></div>
        </div>

        <div class="card">
            <h2>จำนวนนักศึกษาแยกตามชั้นปี</h2>
            <?php if (empty($userStats['by_year'])): ?>
                <p>ยังไม่มีข้อมูล</p>
            <?php else: ?>
                <?php foreach ($userStats['by_year'] as $row): ?>
                    <p>ปี <?= (int) $row['student_year'] ?>: <?= (int) $row['c'] ?> คน</p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>กิจกรรมที่ใกล้ถึง 5 อันดับ</h2>
            <?php if (empty($upcomingList)): ?>
                <p>ไม่มีกิจกรรมที่กำลังจะถึง</p>
            <?php else: ?>
                <?php foreach ($upcomingList as $ev): ?>
                    <div style="padding:10px 0; border-bottom:1px solid #eee;">
                        <strong><?= htmlspecialchars($ev['title']) ?></strong><br>
                        <span style="font-size:13px; color:#666;">
                            🕒 <?= date('d/m/Y H:i', strtotime($ev['start_datetime'])) ?> |
                            👥 <?= (int) $ev['reg_count'] ?><?= $ev['max_participants'] ? ' / ' . (int) $ev['max_participants'] : '' ?> คน
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>