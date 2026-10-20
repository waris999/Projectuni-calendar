<?php
require_once __DIR__ . '/../src/security.php';
/* session initialized by security.php */
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

// ชั่วโมงสะสม (เฉพาะ student)
$currentAcademicYear = currentAcademicYear();
$totalHours = 0;
$requiredHours = 0;
if (!$isAdmin) {
    $totalHours = $eventModel->getTotalHours($_SESSION['user_id'], $currentAcademicYear);
    $stmt = $pdo->prepare("SELECT required_hours FROM hour_goals WHERE student_year = :year AND academic_year = :ay");
    $stmt->execute(['year' => $_SESSION['student_year'], 'ay' => $currentAcademicYear]);
    $goal = $stmt->fetch();
    $requiredHours = $goal ? (float)$goal['required_hours'] : 0;
}

$notifModel = new Notification($pdo);
$unreadCount = $isAdmin ? 0 : $notifModel->countUnread($_SESSION['user_id']);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">
<title>หน้าหลัก | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php">
            <img src="<?= $avatarSrc ?>" alt="รูปโปรไฟล์" width="70" height="70" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';">
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
            <a href="admin_hours.php">⏱️ ชั่วโมงกิจกรรม</a>
            <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <?php else: ?>
            <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
            <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <?php endif; ?>
        <a href="notifications.php">🔔 แจ้งเตือน<?= $unreadCount > 0 ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?></a>
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
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
            <?php if (!$isAdmin): ?>
            <div class="card">
            <h2>ชั่วโมงกิจกรรมสะสม ปีการศึกษา <?= $currentAcademicYear ?></h2>
            <?php
                $percent = $requiredHours > 0 ? min(100, round(($totalHours / $requiredHours) * 100)) : 100;
                $barColor = $percent >= 100 ? '#1e5631' : ($percent >= 50 ? '#f39c12' : '#c0392b');
            ?>
            <div style="margin-bottom: 8px; font-size: 15px;">
                <strong style="color: <?= $barColor ?>;"><?= $totalHours ?></strong>
                <?php if ($requiredHours > 0): ?>
                    / <?= $requiredHours ?> ชั่วโมง
                <?php else: ?>
                    ชั่วโมง (ยังไม่ได้กำหนดเป้าหมาย)
                <?php endif; ?>
            </div>
            <?php if ($requiredHours > 0): ?>
            <div style="background:#eee; border-radius:10px; height:14px; overflow:hidden;">
                <div style="background:<?= $barColor ?>; width:<?= $percent ?>%; height:100%; border-radius:10px; transition: width 0.5s;"></div>
            </div>
                        <div style="font-size:12px; color:#666; margin-top:6px;">
                <?php if ($percent >= 100): ?>
                    ✅ ครบตามเกณฑ์แล้ว!
                    <a href="my_cert.php?ay=<?= htmlspecialchars($currentAcademicYear, ENT_QUOTES, 'UTF-8') ?>" class="btn"
                    style="display:inline-block; margin-top:10px; font-size:13px;">
                        📄 ดู / พิมพ์ใบรับรอง
                    </a>
                <?php else: ?>
                    ต้องการอีก <?= max(0, $requiredHours - $totalHours) ?> ชั่วโมง
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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

<script src="js/mobile-layout.js?v=<?= filemtime(__DIR__ . '/js/mobile-layout.js') ?>"></script>
</body>
</html>
