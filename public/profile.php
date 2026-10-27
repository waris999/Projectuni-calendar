<?php
require_once __DIR__ . '/../src/security.php';
/* session initialized by security.php */
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/Avatar.php';
require_once __DIR__ . '/../src/Notification.php';

$userModel  = new User($pdo);
$notifModel = new Notification($pdo);
$user       = $userModel->findById($_SESSION['user_id']);

$isAdmin     = $_SESSION['role'] === 'admin';
$unreadCount = $isAdmin ? 0 : $notifModel->countUnread($_SESSION['user_id']);

$error       = '';
$success     = '';
$pwError     = '';
$pwSuccess   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Use an ordinary POST for avatar changes as well as signup.
    if (isset($_POST['save_avatar'])) {
        try {
            updateEncodedAvatar($pdo, (int)$_SESSION['user_id'], avatarDataFromPost());
            $user = $userModel->findById($_SESSION['user_id']);
            $success = 'อัปเดตรูปโปรไฟล์สำเร็จ';
        } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
        catch (Throwable $e) { error_log('Avatar update failed: ' . get_class($e)); $error = 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่'; }
    }

    // ===== บันทึกข้อมูลส่วนตัว =====
    if (isset($_POST['save_profile'])) {
        $fullname = trim($_POST['fullname'] ?? '');
        $phone    = trim($_POST['phone']    ?? '');
        $email    = trim($_POST['email']    ?? '');

        if ($fullname === '') {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        } elseif ($phone !== '' && !preg_match('/^[0-9]{9,10}$/', $phone)) {
            $error = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 9-10 หลัก';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'รูปแบบอีเมลไม่ถูกต้อง';
        } else {
            $userModel->updateProfile($_SESSION['user_id'], $fullname, $phone, $email);
            $_SESSION['fullname'] = $fullname;
            $success = 'บันทึกข้อมูลสำเร็จ';
            $user    = $userModel->findById($_SESSION['user_id']);
        }
    }

    // ===== เปลี่ยนรหัสผ่าน =====
    if (isset($_POST['change_password'])) {
        $oldPwd     = $_POST['old_password']      ?? '';
        $newPwd     = $_POST['new_password']      ?? '';
        $confirmPwd = $_POST['confirm_new_password'] ?? '';

        if ($oldPwd === '' || $newPwd === '' || $confirmPwd === '') {
            $pwError = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
        } elseif (strlen($newPwd) < 6) {
            $pwError = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        } elseif ($newPwd !== $confirmPwd) {
            $pwError = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $result = $userModel->changePassword($_SESSION['user_id'], $oldPwd, $newPwd);
            if ($result === 'success') {
                $pwSuccess = 'เปลี่ยนรหัสผ่านสำเร็จ';
            } elseif ($result === 'wrong_password') {
                $pwError = 'รหัสผ่านเดิมไม่ถูกต้อง';
            } else {
                $pwError = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        }
    }
}

// Resolve either a filename or a legacy relative path stored in profile_image.
// The file must exist inside public/img/avatars; otherwise use the default avatar.
$defaultAvatar = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ddd%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fsvg%3E';
$avatarSrc = $defaultAvatar;
$storedAvatar = trim((string)($user['profile_image'] ?? ''));
if ($storedAvatar !== '') {
    $avatarFilename = basename(str_replace('\\', '/', $storedAvatar));
    // Only allow expected image filenames; never use a database value as an arbitrary URL.
    if (preg_match('/\A[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z/i', $avatarFilename)) {
        $avatarFile = __DIR__ . '/img/avatars/' . $avatarFilename;
        if (is_file($avatarFile)) {
            $avatarSrc = 'img/avatars/' . rawurlencode($avatarFilename);
        }
    }
}

require_once __DIR__ . '/../src/Avatar.php';
$avatarSrc = currentAvatarUrl($pdo, (int)$_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="UTF-8">
<title>ข้อมูลส่วนตัว | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<style>
    #registration-avatar-clear[hidden] { display: none !important; }
    .profile-card { max-width: 480px; }
    .avatar-upload-wrap { text-align: center; margin-bottom: 8px; }
    .avatar-upload-wrap img {
        width: 110px; height: 110px; border-radius: 50%;
        object-fit: cover; border: 3px solid #e6f0e9; margin-bottom: 10px;
    }
    .avatar-upload-wrap input[type="file"] { display: none; }
    .avatar-upload-wrap label.upload-btn {
        display: inline-block; background: #f0f4f1; color: #1e5631;
        padding: 6px 16px; border-radius: 6px; font-size: 13px;
        cursor: pointer; border: 1px solid #cfe0d5;
    }
    .avatar-upload-wrap label.upload-btn:hover { background: #e6f0e9; }
    .avatar-hint { font-size: 12px; color: #888; text-align: center; }
    .profile-form label {
        display: block; font-size: 13px; color: #444;
        margin-bottom: 4px; margin-top: 14px;
    }
    .profile-form input {
        width: 100%; padding: 10px; border: 1px solid #ccc;
        border-radius: 6px; font-size: 14px; box-sizing: border-box;
    }
    .profile-form input[readonly] { background: #f4f6f5; color: #888; }
    .msg-error {
        background: #fdecea; color: #b71c1c;
        padding: 8px 12px; border-radius: 6px; font-size: 13px;
        margin-bottom: 14px; max-width: 480px;
    }
    .msg-success {
        background: #e6f4ea; color: #1e5631;
        padding: 8px 12px; border-radius: 6px; font-size: 13px;
        margin-bottom: 14px; max-width: 480px;
    }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php">
            <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="รูปโปรไฟล์" width="70" height="70" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';">
        </a>
        <div class="name"><?= htmlspecialchars($user['fullname']) ?></div>
        <div class="role">
            <?php if ($isAdmin): ?>
                เจ้าหน้าที่
            <?php else: ?>
                นักศึกษา<?= !empty($user['student_year'])
                    ? ' ชั้นปีที่ ' . (int)$user['student_year'] : '' ?>
            <?php endif; ?>
        </div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
            <a href="admin_hours.php">⏱️ ชั่วโมงกิจกรรม</a>
            <a href="admin_attendance.php">📝 อนุมัติชั่วโมง</a>
            <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <?php else: ?>
            <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
            <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <?php endif; ?>
        <a href="notifications.php">
            🔔 แจ้งเตือน<?= $unreadCount > 0
                ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?>
        </a>
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / ข้อมูลส่วนตัว</div>
    <div class="content">

        <!-- ข้อความ error/success รูปโปรไฟล์และข้อมูลส่วนตัว -->
        <?php if ($error): ?>
            <div class="msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- รูปโปรไฟล์ -->
        <div class="card profile-card">
            <h2>รูปโปรไฟล์</h2>
            <form method="POST" enctype="application/x-www-form-urlencoded">
<?= csrfField() ?>
                <div class="avatar-upload-wrap">
                    <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="avatar" id="avatarPreview" onerror="this.onerror=null;this.src=<?= htmlspecialchars(json_encode($defaultAvatar), ENT_QUOTES, 'UTF-8') ?>">
                    <br>
                    <label class="upload-btn" for="avatarInput">เปลี่ยนรูป</label>
                    <input type="file" id="avatarInput"
                           accept="image/jpeg,image/png,image/webp"
>
                </div>
                <div class="avatar-hint">รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 2MB</div>
                <input type="hidden" name="avatar_data" value="">
                <input type="hidden" name="avatar_selected" value="0">
                <input type="hidden" name="save_avatar" value="1">
                <p id="registration-avatar-error" role="status" style="color:#b71c1c;font-size:13px;margin:10px 0;"></p>
                <button type="button" id="registration-avatar-clear" class="btn" hidden>ยกเลิกรูปที่เลือก</button>
                <button type="submit" class="btn" style="margin-top:12px;">บันทึกรูปโปรไฟล์</button>
            </form>
        </div>

        <!-- ข้อมูลส่วนตัว -->
        <div class="card profile-card">
            <h2>แก้ไขข้อมูลส่วนตัว</h2>
            <form method="POST" class="profile-form">
<?= csrfField() ?>
                <label>รหัสนักศึกษา / Username</label>
                <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly>

                <?php if (!$isAdmin): ?>
                <label>ชั้นปี</label>
                <input type="text" value="ปี <?= (int)$user['student_year'] ?>" readonly>
                <?php endif; ?>

                <label>ชื่อ-นามสกุล</label>
                <input type="text" name="fullname"
                       value="<?= htmlspecialchars($user['fullname']) ?>" required>

                <label>เบอร์โทรศัพท์</label>
                <input type="text" name="phone"
                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                       placeholder="เช่น 0812345678">

                <label>อีเมล</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                       placeholder="เช่น example@email.com">

                <button type="submit" name="save_profile" class="btn"
                        style="width:100%; margin-top:18px;">
                    บันทึกข้อมูล
                </button>
            </form>
        </div>

        <!-- เปลี่ยนรหัสผ่าน -->
        <div class="card profile-card">
            <h2>เปลี่ยนรหัสผ่าน</h2>

            <?php if ($pwError): ?>
                <div class="msg-error"><?= htmlspecialchars($pwError) ?></div>
            <?php endif; ?>
            <?php if ($pwSuccess): ?>
                <div class="msg-success"><?= htmlspecialchars($pwSuccess) ?></div>
            <?php endif; ?>

            <form method="POST" class="profile-form">
<?= csrfField() ?>
                <label>รหัสผ่านเดิม</label>
                <input type="password" name="old_password" required>

                <label>รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)</label>
                <input type="password" name="new_password" required>

                <label>ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_new_password" required>

                <button type="submit" name="change_password" class="btn"
                        style="width:100%; margin-top:18px;">
                    เปลี่ยนรหัสผ่าน
                </button>
            </form>
        </div>

    </div>
</div>

<script src="js/mobile-layout.js?v=<?= filemtime(__DIR__ . '/js/mobile-layout.js') ?>"></script>
<script src="js/registration-avatar.js?v=<?= filemtime(__DIR__ . '/js/registration-avatar.js') ?>"></script>
</body>
</html>
