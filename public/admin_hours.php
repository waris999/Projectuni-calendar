<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Event.php';
require_once __DIR__ . '/../src/Notification.php';

$eventModel = new Event($pdo);
$notifModel = new Notification($pdo);
$unreadCount = 0;

$currentAY = (string)(date('Y') + 543);
$selectedAY = $_GET['ay'] ?? $currentAY;

$summary = $eventModel->getHoursSummaryAll($selectedAY);

// ดึงเป้าหมายทุกชั้นปีของปีที่เลือก
$goalStmt = $pdo->prepare("SELECT student_year, required_hours FROM hour_goals WHERE academic_year = :ay");
$goalStmt->execute(['ay' => $selectedAY]);
$goals = [];
foreach ($goalStmt->fetchAll() as $g) {
    $goals[$g['student_year']] = (float)$g['required_hours'];
}

// บันทึกเป้าหมายที่แก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_goals'])) {
    foreach ($_POST['goals'] as $year => $hours) {
        $pdo->prepare(
            "INSERT INTO hour_goals (student_year, academic_year, required_hours)
            VALUES (:year, :ay, :hours)
            ON DUPLICATE KEY UPDATE required_hours = :hours2"
        )->execute([
            'year'   => (int)$year,
            'ay'     => $selectedAY,
            'hours'  => (float)$hours,
            'hours2' => (float)$hours,
        ]);
    }
    header("Location: admin_hours.php?ay=$selectedAY");
    exit;
}

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ชั่วโมงกิจกรรม | Uni Calendar</title>
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
        <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
        <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <a href="admin_hours.php" class="active">⏱️ ชั่วโมงกิจกรรม</a>
        <a href="notifications.php">🔔 แจ้งเตือน</a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / ชั่วโมงกิจกรรม</div>
    <div class="content">

        <!-- กำหนดเป้าหมายชั่วโมงต่อชั้นปี -->
        <div class="card">
            <h2>กำหนดเป้าหมายชั่วโมง ปีการศึกษา <?= $selectedAY ?></h2>
            <form method="POST" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                <?php foreach ([1,2,3,4] as $y): ?>
                    <div>
                        <label style="font-size:13px; display:block; margin-bottom:4px;">ปี <?= $y ?></label>
                        <input type="number" name="goals[<?= $y ?>]"
                            value="<?= $goals[$y] ?? 6 ?>" min="0" step="0.5"
                            style="width:80px; padding:8px; border:1px solid #ccc; border-radius:6px; font-size:13px;">
                    </div>
                <?php endforeach; ?>
                <button type="submit" name="save_goals" class="btn">บันทึกเป้าหมาย</button>
            </form>
        </div>

        <!-- ตารางสรุปรายคน -->
        <div class="card">
            <h2>สรุปชั่วโมงสะสม — นักศึกษาทั้งหมด</h2>

            <form method="GET" style="display:flex; gap:10px; margin-bottom:16px; align-items:center;">
                <label style="font-size:13px;">ปีการศึกษา:</label>
                <select name="ay" style="padding:7px 10px; border:1px solid #ccc; border-radius:6px; font-size:13px;">
                    <?php for ($y = (int)$currentAY; $y >= (int)$currentAY - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= (string)$y === $selectedAY ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn">ดู</button>
            </form>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ชั้นปี</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>เข้าร่วมกิจกรรม</th>
                        <th>ชั่วโมงสะสม</th>
                        <th>เป้าหมาย</th>
                        <th>สถานะ</th>
                        <th>ใบเกียรติบัตร</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summary)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:#999; padding:20px;">
                                ไม่มีข้อมูล
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($summary as $s):
                            $goal = $goals[$s['student_year']] ?? 0;
                            $done = $goal > 0 && $s['total_hours'] >= $goal;
                        ?>
                        <tr>
                            <td>ปี <?= (int)$s['student_year'] ?></td>
                            <td><?= htmlspecialchars($s['username']) ?></td>
                            <td><?= htmlspecialchars($s['fullname']) ?></td>
                            <td style="text-align:center;"><?= (int)$s['attended_count'] ?> กิจกรรม</td>
                            <td style="text-align:center; font-weight:600;"><?= $s['total_hours'] ?> ชม.</td>
                            <td style="text-align:center;"><?= $goal > 0 ? $goal . ' ชม.' : '-' ?></td>

                            <!-- คอลัมน์สถานะ -->
                            <td style="text-align:center;">
                                <?php if ($goal <= 0): ?>
                                    <span style="color:#999; font-size:12px;">ไม่ได้กำหนด</span>
                                <?php elseif ($done): ?>
                                    <span style="background:#e6f4ea; color:#1e5631; padding:3px 10px; border-radius:12px; font-size:12px;">
                                        ✅ ครบแล้ว
                                    </span>
                                <?php else: ?>
                                    <span style="background:#fdecea; color:#b71c1c; padding:3px 10px; border-radius:12px; font-size:12px;">
                                        ขาดอีก <?= $goal - $s['total_hours'] ?> ชม.
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- คอลัมน์ปุ่ม PDF -->
                            <td style="text-align:center;">
                                <a href="generate_cert.php?user_id=<?= (int)$s['id'] ?>"
                                target="_blank"
                                class="btn"
                                style="font-size:12px; padding:5px 12px;">
                                    📄 PDF
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

</body>
</html>