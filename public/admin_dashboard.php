<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Event.php';
require_once __DIR__ . '/../src/Notification.php';

$userModel  = new User($pdo);
$eventModel = new Event($pdo);
$notifModel = new Notification($pdo);
$unreadCount = 0;

// ---- ตัวเลขสถิติหลัก ----
$userStats        = $userModel->getStats();
$totalEvents      = (int) $pdo->query("SELECT COUNT(*) c FROM events")->fetch()['c'];
$upcomingEvents   = (int) $pdo->query("SELECT COUNT(*) c FROM events WHERE start_datetime >= NOW()")->fetch()['c'];
$totalRegistrations = (int) $pdo->query("SELECT COUNT(*) c FROM registrations WHERE status IN ('registered','attended')")->fetch()['c'];
$totalAttended    = (int) $pdo->query("SELECT COUNT(*) c FROM registrations WHERE status = 'attended'")->fetch()['c'];

// ---- ข้อมูลสำหรับกราฟ ----
$regStats     = $eventModel->getRegistrationStats(5);
$monthlyStats = $eventModel->getMonthlyEventCount();

// กราฟแท่ง: ชื่อกิจกรรม, ลงทะเบียน, เช็คอิน
$chartEventLabels   = [];
$chartRegData       = [];
$chartAttendedData  = [];
foreach ($regStats as $r) {
    // ตัดชื่อยาวเกิน 14 ตัวอักษรให้พอดีกราฟ
    $chartEventLabels[]  = mb_strlen($r['title']) > 14
        ? mb_substr($r['title'], 0, 14) . '…'
        : $r['title'];
    $chartRegData[]      = (int)$r['total_reg'];
    $chartAttendedData[] = (int)$r['total_attended'];
}

// กราฟโดนัท: นักศึกษาแยกชั้นปี
$chartYearLabels = [];
$chartYearData   = [];
foreach ($userStats['by_year'] as $row) {
    $chartYearLabels[] = 'ปี ' . (int)$row['student_year'];
    $chartYearData[]   = (int)$row['c'];
}

// กราฟเส้น: กิจกรรมรายเดือน
$chartMonthLabels = [];
$chartMonthData   = [];
$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
foreach ($monthlyStats as $m) {
    [,$mon] = explode('-', $m['month']);
    $chartMonthLabels[] = $thaiMonths[(int)$mon];
    $chartMonthData[]   = (int)$m['count'];
}

// 5 กิจกรรมใกล้ถึง
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    .chart-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .chart-card h3 {
        font-size: 14px;
        color: #1e5631;
        margin-bottom: 14px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e6f0e9;
    }
    .chart-wrap {
        position: relative;
        height: 220px;
    }
</style>
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
        <a href="admin_hours.php">⏱️ ชั่วโมงกิจกรรม</a>
        <a href="notifications.php">🔔 แจ้งเตือน</a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / แดชบอร์ด</div>
    <div class="content">

        <!-- ตัวเลขสถิติหลัก -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="num"><?= $userStats['total_students'] ?></div>
                <div class="label">นักศึกษาทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $totalEvents ?></div>
                <div class="label">กิจกรรมทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $upcomingEvents ?></div>
                <div class="label">กิจกรรมที่กำลังจะถึง</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $totalRegistrations ?></div>
                <div class="label">ยอดลงทะเบียนรวม</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $totalAttended ?></div>
                <div class="label">เช็คอินสำเร็จ</div>
            </div>
            <div class="stat-card">
                <div class="num">
                    <?= $totalRegistrations > 0
                        ? round(($totalAttended / $totalRegistrations) * 100) . '%'
                        : '0%' ?>
                </div>
                <div class="label">อัตราเช็คอิน</div>
            </div>
        </div>

        <!-- กราฟ row 1 -->
        <div class="chart-grid">

            <!-- กราฟแท่ง: ลงทะเบียน vs เช็คอิน -->
            <div class="chart-card">
                <h3>📊 ลงทะเบียน vs เช็คอิน (5 กิจกรรมล่าสุด)</h3>
                <?php if (empty($regStats)): ?>
                    <p style="color:#999; font-size:13px; text-align:center; padding:60px 0;">ยังไม่มีข้อมูลกิจกรรม</p>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="chartRegVsAttend"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <!-- กราฟโดนัท: สัดส่วนนักศึกษาแยกชั้นปี -->
            <div class="chart-card">
                <h3>🎓 สัดส่วนนักศึกษาแยกชั้นปี</h3>
                <?php if (empty($userStats['by_year'])): ?>
                    <p style="color:#999; font-size:13px; text-align:center; padding:60px 0;">ยังไม่มีข้อมูลนักศึกษา</p>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="chartYearDist"></canvas>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- กราฟ row 2 -->
        <div class="chart-grid">

            <!-- กราฟเส้น: กิจกรรมรายเดือน -->
            <div class="chart-card">
                <h3>📅 จำนวนกิจกรรมรายเดือน (6 เดือนล่าสุด)</h3>
                <?php if (empty($monthlyStats)): ?>
                    <p style="color:#999; font-size:13px; text-align:center; padding:60px 0;">ยังไม่มีข้อมูล</p>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="chartMonthly"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <!-- กราฟแท่งนอน: นักศึกษาแยกชั้นปี (ตัวเลขละเอียด) -->
            <div class="chart-card">
                <h3>👥 จำนวนนักศึกษาแยกชั้นปี</h3>
                <?php if (empty($userStats['by_year'])): ?>
                    <p style="color:#999; font-size:13px; text-align:center; padding:60px 0;">ยังไม่มีข้อมูลนักศึกษา</p>
                <?php else: ?>
                    <div class="chart-wrap">
                        <canvas id="chartYearBar"></canvas>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ตาราง 5 กิจกรรมใกล้ถึง -->
        <div class="card">
            <h2>กิจกรรมที่ใกล้ถึง 5 อันดับ</h2>
            <?php if (empty($upcomingList)): ?>
                <p style="color:#999; font-size:14px;">ไม่มีกิจกรรมที่กำลังจะถึง</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ชื่อกิจกรรม</th>
                            <th>วันที่</th>
                            <th>ลงทะเบียน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingList as $ev): ?>
                            <tr>
                                <td><?= htmlspecialchars($ev['title']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($ev['start_datetime'])) ?></td>
                                <td style="text-align:center;">
                                    <?= (int)$ev['reg_count'] ?>
                                    <?= $ev['max_participants']
                                        ? ' / ' . (int)$ev['max_participants']
                                        : '' ?>
                                    คน
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
const GREEN       = '#1e5631';
const GREEN_LIGHT = '#4caf7d';
const ORANGE      = '#f39c12';
const RED         = '#e74c3c';
const BLUE        = '#3498db';
const PURPLE      = '#9b59b6';

// ---- กราฟแท่ง: ลงทะเบียน vs เช็คอิน ----
<?php if (!empty($regStats)): ?>
new Chart(document.getElementById('chartRegVsAttend'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartEventLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            {
                label: 'ลงทะเบียน',
                data: <?= json_encode($chartRegData) ?>,
                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                borderColor: BLUE,
                borderWidth: 1,
                borderRadius: 4,
            },
            {
                label: 'เช็คอินจริง',
                data: <?= json_encode($chartAttendedData) ?>,
                backgroundColor: 'rgba(30, 86, 49, 0.75)',
                borderColor: GREEN,
                borderWidth: 1,
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 12 } } }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});
<?php endif; ?>

// ---- กราฟโดนัท: สัดส่วนชั้นปี ----
<?php if (!empty($userStats['by_year'])): ?>
new Chart(document.getElementById('chartYearDist'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartYearLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($chartYearData) ?>,
            backgroundColor: [GREEN, BLUE, ORANGE, PURPLE],
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 12 }, padding: 14 }
            }
        },
        cutout: '60%'
    }
});
<?php endif; ?>

// ---- กราฟเส้น: กิจกรรมรายเดือน ----
<?php if (!empty($monthlyStats)): ?>
new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartMonthLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'จำนวนกิจกรรม',
            data: <?= json_encode($chartMonthData) ?>,
            borderColor: GREEN,
            backgroundColor: 'rgba(30, 86, 49, 0.1)',
            borderWidth: 2,
            pointBackgroundColor: GREEN,
            pointRadius: 5,
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});
<?php endif; ?>

// ---- กราฟแท่งนอน: จำนวนนักศึกษาแยกชั้นปี ----
<?php if (!empty($userStats['by_year'])): ?>
new Chart(document.getElementById('chartYearBar'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartYearLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'จำนวนนักศึกษา',
            data: <?= json_encode($chartYearData) ?>,
            backgroundColor: [
                'rgba(30,86,49,0.75)',
                'rgba(52,152,219,0.75)',
                'rgba(243,156,18,0.75)',
                'rgba(155,89,182,0.75)'
            ],
            borderColor: [GREEN, BLUE, ORANGE, PURPLE],
            borderWidth: 1,
            borderRadius: 6,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>