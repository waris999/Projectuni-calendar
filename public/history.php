<?php
require_once __DIR__ . '/../src/security.php';
/* session initialized by security.php */
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
require_once __DIR__ . '/../src/EventPhoto.php';
require_once __DIR__ . '/../src/Notification.php';

$eventModel = new Event($pdo);
$photoModel = new EventPhoto($pdo);
$notifModel = new Notification($pdo);

$history     = $eventModel->getHistoryForUser($_SESSION['user_id']);
$unreadCount = $notifModel->countUnread($_SESSION['user_id']);

// ชั่วโมงสะสมสำหรับตรวจสอบสิทธิ์ใบเกียรติบัตร
$currentAY        = currentAcademicYear();
$totalHoursForCert = $eventModel->getTotalHours($_SESSION['user_id'], $currentAY);

$goalStmt = $pdo->prepare(
    "SELECT required_hours FROM hour_goals WHERE student_year = :year AND academic_year = :ay"
);
$goalStmt->execute([
    'year' => $_SESSION['student_year'],
    'ay'   => $currentAY,
]);
$goal           = $goalStmt->fetch();
$requiredForCert = $goal ? (float)$goal['required_hours'] : 0;

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">
<title>ประวัติกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<style>
    .history-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid #eee;
        gap: 12px;
        flex-wrap: wrap;
    }
    .history-item:last-child { border-bottom: none; }
    .history-item .info strong { display: block; margin-bottom: 4px; }
    .history-item .info span  { font-size: 13px; color: #666; }

    .badge { font-size: 12px; padding: 4px 12px; border-radius: 12px; white-space: nowrap; font-weight: 500; }
    .badge-attended  { background: #e6f4ea; color: #1e5631; }
    .badge-registered { background: #fff4e5; color: #b8860b; }
    .badge-cancelled  { background: #f4f4f4; color: #999; }
    .badge-missed     { background: #fdecea; color: #b71c1c; }

    .action-wrap { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    .cert-banner {
        background: #e6f4ea;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        padding: 14px 18px;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .cert-banner span { font-size: 14px; color: #1e5631; }

    .hours-summary {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 0;
        border-bottom: 1px solid #eee;
        margin-bottom: 4px;
        flex-wrap: wrap;
    }
    .hours-summary .num   { font-size: 28px; font-weight: 700; color: #1e5631; line-height: 1; }
    .hours-summary .label { font-size: 13px; color: #666; margin-top: 4px; }
    .progress-wrap { flex: 1; min-width: 160px; }
    .progress-bar-bg {
        background: #eee;
        border-radius: 10px;
        height: 12px;
        overflow: hidden;
        margin-bottom: 4px;
    }
    .progress-bar-fill { height: 100%; border-radius: 10px; transition: width 0.5s; }
    .progress-label    { font-size: 12px; color: #666; }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php">
            <img src="<?= $avatarSrc ?>" alt="รูปโปรไฟล์" width="70" height="70" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';">
        </a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">
            นักศึกษา<?= !empty($_SESSION['student_year'])
                ? ' ชั้นปีที่ ' . (int)$_SESSION['student_year'] : '' ?>
        </div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
        <a href="history.php" class="active">🕘 ประวัติกิจกรรม</a>
        <a href="notifications.php">🔔 แจ้งเตือน<?= $unreadCount > 0
            ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?></a>
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / ประวัติกิจกรรม</div>
    <div class="content">

        <!-- Banner ใบเกียรติบัตร -->
        <?php if ($requiredForCert > 0 && $totalHoursForCert >= $requiredForCert): ?>
        <div class="cert-banner">
            <span>
                🎉 ยินดีด้วย! คุณสะสมครบ
                <strong><?= $totalHoursForCert ?> ชั่วโมง</strong>
                แล้ว สามารถดาวน์โหลดใบเกียรติบัตรได้เลย
            </span>
            <a href="my_cert.php" class="btn" style="font-size:13px; white-space:nowrap;">
                📄 ดาวน์โหลดใบเกียรติบัตร
            </a>
        </div>
        <?php endif; ?>

        <!-- Card ชั่วโมงสะสม -->
        <div class="card">
            <h2>ชั่วโมงกิจกรรมสะสม ปีการศึกษา <?= $currentAY ?></h2>
            <?php
                $percent  = $requiredForCert > 0
                    ? min(100, round(($totalHoursForCert / $requiredForCert) * 100))
                    : null;
                $barColor = !$percent ? '#1e5631'
                    : ($percent >= 100 ? '#1e5631'
                    : ($percent >= 50  ? '#f39c12' : '#c0392b'));
            ?>
            <div class="hours-summary">
                <div>
                    <div class="num"><?= $totalHoursForCert ?></div>
                    <div class="label">
                        ชั่วโมงสะสม
                        <?php if ($requiredForCert > 0): ?>
                            / <?= $requiredForCert ?> ชั่วโมง
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($requiredForCert > 0): ?>
                <div class="progress-wrap">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill"
                            style="width:<?= $percent ?>%; background:<?= $barColor ?>;"></div>
                    </div>
                    <div class="progress-label">
                        <?php if ($percent >= 100): ?>
                            ✅ ครบตามเกณฑ์แล้ว
                        <?php else: ?>
                            ต้องการอีก <?= max(0, $requiredForCert - $totalHoursForCert) ?> ชั่วโมง
                            (<?= $percent ?>%)
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <div style="font-size:13px; color:#999;">ยังไม่ได้กำหนดเป้าหมายชั่วโมง</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card ประวัติกิจกรรม -->
        <div class="card">
            <h2>ประวัติการเข้าร่วมกิจกรรมทั้งหมด</h2>

            <?php if (empty($history)): ?>
                <p style="color:#999; font-size:14px;">ยังไม่มีประวัติกิจกรรม</p>
            <?php else: ?>
                <?php
                $historyNow = $pdo->query("SELECT NOW() AS now")->fetch()['now'];
                foreach ($history as $ev):
                    $isPast = $ev['end_datetime'] < $historyNow;

                    if ($ev['reg_status'] === 'attended') {
                        $badgeClass = 'badge-attended';
                        $badgeText  = 'เข้าร่วมแล้ว ✓';
                    } elseif ($ev['reg_status'] === 'cancelled') {
                        $badgeClass = 'badge-cancelled';
                        $badgeText  = 'ยกเลิกแล้ว';
                    } elseif ($isPast) {
                        $badgeClass = 'badge-missed';
                        $badgeText  = 'ไม่ได้เช็คอิน';
                    } else {
                        $badgeClass = 'badge-registered';
                        $badgeText  = 'รอเข้าร่วม';
                    }

                    // นับจำนวนรูปในอัลบั้ม
                    $photoCount = count($photoModel->getPhotos($_SESSION['user_id'], $ev['id']));
                ?>
                    <div class="history-item">
                        <div class="info">
                            <strong><?= htmlspecialchars($ev['title']) ?></strong>
                            <span>
                                📍 <?= htmlspecialchars($ev['location']) ?>
                                &nbsp;|&nbsp;
                                🕒 <?= date('d/m/Y H:i', strtotime($ev['start_datetime'])) ?>
                                <?php if (!empty($ev['activity_hours']) && $ev['activity_hours'] > 0): ?>
                                    &nbsp;|&nbsp; ⏱️ <?= (float)$ev['activity_hours'] ?> ชม.
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="action-wrap">
                            <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>

                            <!-- ปุ่มอัลบั้ม (แสดงเฉพาะกิจกรรมที่ลงทะเบียนไม่ใช่ยกเลิก) -->
                            <?php if ($ev['reg_status'] !== 'cancelled'): ?>
                                <a href="event_album.php?event_id=<?= (int)$ev['id'] ?>"
                                class="btn"
                                style="font-size:12px; padding:4px 12px;">
                                    📸<?= $photoCount > 0 ? " ({$photoCount})" : '' ?>
                                </a>
                            <?php endif; ?>

                            <!-- ปุ่มใบเกียรติบัตรต่อกิจกรรม -->
                            <?php if ($ev['reg_status'] === 'attended'): ?>
                                <a href="my_cert.php?event_id=<?= (int)$ev['id'] ?>"
                                class="btn"
                                style="font-size:12px; padding:4px 12px; background:#1a4a2e; color:#fff;">
                                    📄 ใบเกียรติบัตร
                                </a>
                            <?php endif; ?>
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
