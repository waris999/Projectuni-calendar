
<?php
require_once __DIR__ . '/../src/security.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Event.php';
require_once __DIR__ . '/../src/Notification.php';
require_once __DIR__ . '/../src/User.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// ===== ดึงข้อมูลผู้ใช้จากฐานข้อมูลโดยตรง =====
$userModel = new User($pdo);
$currentUser = $userModel->findById($_SESSION['user_id']);

if (!$currentUser) {
    // ไม่มีข้อมูลผู้ใช้แล้ว ให้ล้าง Session
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// อัปเดตข้อมูล Session ให้ตรงกับฐานข้อมูล
$_SESSION['fullname'] = $currentUser['fullname'];
$_SESSION['username'] = $currentUser['username'];
$_SESSION['student_year'] = $currentUser['student_year'] ?? null;
$_SESSION['profile_image'] = $currentUser['profile_image'] ?? null;

// ===== รูปโปรไฟล์เริ่มต้น =====
$defaultAvatar = 'data:image/svg+xml,' . rawurlencode(
    '<svg xmlns="http://www.w3.org/2000/svg"
        width="256" height="256" viewBox="0 0 256 256">
        <circle cx="128" cy="128" r="128" fill="#ccc"/>
        <circle cx="128" cy="96" r="48" fill="#999"/>
        <path d="M32 256v-24c0-42 43-80 96-80s96 38 96 80v24z"
            fill="#999"/>
    </svg>'
);

$avatarSrc = $defaultAvatar;

// ===== ตรวจสอบรูปโปรไฟล์จากฐานข้อมูล =====
$avatarValue = trim((string)($currentUser['profile_image'] ?? ''));

if ($avatarValue !== '') {

    // รองรับชื่อไฟล์และ Path เช่น:
    // avatar_abc.jpg
    // img/avatars/avatar_abc.jpg
    // public/img/avatars/avatar_abc.jpg
    $filename = basename(str_replace('\\', '/', $avatarValue));

    // ตรวจสอบชื่อไฟล์เพื่อป้องกัน Path ที่ไม่ปลอดภัย
    if (preg_match(
        '/\A[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)\z/i',
        $filename
    )) {

        $avatarPath = __DIR__ . '/img/avatars/' . $filename;

        if (is_file($avatarPath)) {
            $avatarSrc = 'img/avatars/' . rawurlencode($filename);
        }
    }
}

// ===== ระบบกิจกรรม =====
$eventModel = new Event($pdo);

$myEvents = $eventModel->getRegisteredByUser(
    $_SESSION['user_id']
);

// ===== ชั่วโมงกิจกรรมสะสม =====
$currentAcademicYear = currentAcademicYear();

$totalHours = 0;
$requiredHours = 0;

if (!$isAdmin) {

    $totalHours = $eventModel->getTotalHours(
        $_SESSION['user_id'],
        $currentAcademicYear
    );

    $stmt = $pdo->prepare(
        "SELECT required_hours
         FROM hour_goals
         WHERE student_year = :year
         AND academic_year = :ay"
    );

    $stmt->execute([
        'year' => $_SESSION['student_year'],
        'ay' => $currentAcademicYear
    ]);

    $goal = $stmt->fetch();

    $requiredHours = $goal
        ? (float)$goal['required_hours']
        : 0;
}

// ===== ระบบแจ้งเตือน =====
$notifModel = new Notification($pdo);

$unreadCount = $isAdmin
    ? 0
    : $notifModel->countUnread($_SESSION['user_id']);

// ===== คำนวณความคืบหน้าชั่วโมงกิจกรรม =====
$percent = $requiredHours > 0
    ? min(100, round(($totalHours / $requiredHours) * 100))
    : 100;

$barColor = $percent >= 100
    ? '#1e5631'
    : ($percent >= 50 ? '#f39c12' : '#c0392b');

require_once __DIR__ . '/../src/Avatar.php';
$avatarSrc = currentAvatarUrl($pdo, (int)$_SESSION['user_id']);
require_once __DIR__ . '/../src/Avatar.php';
$avatarSrc = currentAvatarUrl($pdo, (int)$_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="UTF-8">

    <title>หน้าหลัก | Uni Calendar</title>

    <link rel="stylesheet"
          href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>

<body class="app-layout">

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">

    <div class="profile">

        <a href="profile.php">

            <img
                src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
                alt="รูปโปรไฟล์"
                width="70"
                height="70"
                style="border-radius:50%;object-fit:cover;"
                onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode($defaultAvatar), ENT_QUOTES, 'UTF-8') ?>;"
            >

        </a>

        <div class="name">
            <?= htmlspecialchars($currentUser['fullname']) ?>
        </div>

        <div class="role">

            <?php if ($isAdmin): ?>

                เจ้าหน้าที่

            <?php else: ?>

                นักศึกษา
                <?= !empty($currentUser['student_year'])
                    ? ' ชั้นปีที่ ' . (int)$currentUser['student_year']
                    : ''
                ?>

            <?php endif; ?>

        </div>

    </div>

    <!-- ===== MENU ===== -->
    <nav>

        <a href="dashboard.php" class="active">
            🏠 หน้าหลัก
        </a>

        <a href="calendar.php">
            📅 ปฏิทินกิจกรรม
        </a>

        <?php if ($isAdmin): ?>

            <a href="admin_dashboard.php">
                📊 แดชบอร์ด
            </a>

            <a href="admin_hours.php">
                ⏱️ ชั่วโมงกิจกรรม
            </a>
            <a href="admin_attendance.php">📝 อนุมัติชั่วโมง</a>

            <a href="admin_users.php">
                👥 จัดการผู้ใช้
            </a>

        <?php else: ?>

            <a href="checkin.php">
                ✅ เช็คอินกิจกรรม
            </a>

            <a href="history.php">
                🕘 ประวัติกิจกรรม
            </a>

        <?php endif; ?>

        <a href="notifications.php">
            🔔 แจ้งเตือน
            <?= $unreadCount > 0
                ? '<span class="nav-badge">' . (int)$unreadCount . '</span>'
                : ''
            ?>
        </a>

        <form
            method="POST"
            action="logout.php"
            class="logout-form"
            onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');"
        >

            <?= csrfField() ?>

            <button type="submit" class="logout-button">
                🚪 ออกจากระบบ
            </button>

        </form>

    </nav>

</aside>

<!-- ===== MAIN CONTENT ===== -->
<div class="main">

    <div class="topbar">
        Home / หน้าหลัก
    </div>

    <div class="content">

        <!-- ===== ข้อมูลของฉัน ===== -->
        <div class="card">

            <h2>ข้อมูลของฉัน</h2>

            <p>
                ชื่อ:
                <?= htmlspecialchars($currentUser['fullname']) ?>
            </p>

            <p>
                รหัสนักศึกษา:
                <?= htmlspecialchars($currentUser['username']) ?>
            </p>

            <p>
                สถานะ:
                <?php if ($isAdmin): ?>
                    เจ้าหน้าที่
                <?php else: ?>
                    นักศึกษา
                    <?= !empty($currentUser['student_year'])
                        ? ' ชั้นปีที่ ' . (int)$currentUser['student_year']
                        : ''
                    ?>
                <?php endif; ?>
            </p>

            <!-- ===== ชั่วโมงกิจกรรมสะสม ===== -->
            <?php if (!$isAdmin): ?>

                <div class="card">

                    <h2>
                        ชั่วโมงกิจกรรมสะสม
                        ปีการศึกษา <?= (int)$currentAcademicYear ?>
                    </h2>

                    <div style="margin-bottom:8px;font-size:15px;">

                        <strong style="color:<?= $barColor ?>;">
                            <?= htmlspecialchars((string)$totalHours) ?>
                        </strong>

                        <?php if ($requiredHours > 0): ?>

                            / <?= htmlspecialchars((string)$requiredHours) ?>
                            ชั่วโมง

                        <?php else: ?>

                            ชั่วโมง (ยังไม่ได้กำหนดเป้าหมาย)

                        <?php endif; ?>

                    </div>

                    <?php if ($requiredHours > 0): ?>

                        <div style="
                            background:#eee;
                            border-radius:10px;
                            height:14px;
                            overflow:hidden;
                        ">

                            <div style="
                                background:<?= $barColor ?>;
                                width:<?= $percent ?>%;
                                height:100%;
                                border-radius:10px;
                                transition:width 0.5s;
                            "></div>

                        </div>

                        <div style="
                            font-size:12px;
                            color:#666;
                            margin-top:6px;
                        ">

                            <?php if ($percent >= 100): ?>

                                ✅ ครบตามเกณฑ์แล้ว!

                                <a
                                    href="my_cert.php?ay=<?= urlencode((string)$currentAcademicYear) ?>"
                                    class="btn"
                                    style="
                                        display:inline-block;
                                        margin-top:10px;
                                        font-size:13px;
                                    "
                                >
                                    📄 ดู / พิมพ์ใบรับรอง
                                </a>

                            <?php else: ?>

                                ต้องการอีก
                                <?= max(0, $requiredHours - $totalHours) ?>
                                ชั่วโมง

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

        <!-- ===== กิจกรรมที่กำลังจะถึง ===== -->
        <div class="card">

            <h2>กิจกรรมที่กำลังจะถึง</h2>

            <?php if (empty($myEvents)): ?>

                <p>ยังไม่มีกิจกรรมที่ลงทะเบียนไว้</p>

            <?php else: ?>

                <?php foreach ($myEvents as $ev): ?>

                    <div style="
                        padding:12px 0;
                        border-bottom:1px solid #eee;
                    ">

                        <strong>
                            <?= htmlspecialchars($ev['title']) ?>
                        </strong>

                        <br>

                        <span style="font-size:13px;color:#666;">

                            📍 <?= htmlspecialchars($ev['location']) ?>

                            |

                            🕒 <?= date(
                                'd/m/Y H:i',
                                strtotime($ev['start_datetime'])
                            ) ?>

                        </span>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

            <a
                href="calendar.php"
                class="btn"
                style="margin-top:14px;display:inline-block;"
            >
                ไปหน้าปฏิทิน
            </a>

        </div>

    </div>

</div>

<script src="js/mobile-layout.js?v=<?= filemtime(__DIR__ . '/js/mobile-layout.js') ?>"></script>

</body>
</html>
