<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';
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

    // ===== อัปโหลดรูปโปรไฟล์ =====
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $fileType     = mime_content_type($_FILES['avatar']['tmp_name']);

        if (!in_array($fileType, $allowedTypes, true)) {
            $error = 'รองรับเฉพาะไฟล์ JPG, PNG, WEBP เท่านั้น';
        } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $error = 'ขนาดไฟล์ต้องไม่เกิน 2MB';
        } else {
            $ext         = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $newFilename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $destination = __DIR__ . '/img/avatars/' . $newFilename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                if (!empty($user['profile_image'])) {
                    $oldPath = __DIR__ . '/img/avatars/' . $user['profile_image'];
                    if (file_exists($oldPath)) unlink($oldPath);
                }
                $userModel->updateAvatar($_SESSION['user_id'], $newFilename);
                $_SESSION['profile_image'] = $newFilename;
                $success = 'อัปเดตรูปโปรไฟล์สำเร็จ';
                $user    = $userModel->findById($_SESSION['user_id']);
            } else {
                $error = 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่';
            }
        }
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

$avatarSrc = !empty($user['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($user['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ข้อมูลส่วนตัว | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<style>
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
            <img src="<?= $avatarSrc ?>" alt="avatar">
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
            <a href="admin_users.php">👥 จัดการผู้ใช้</a>
            <a href="admin_hours.php">⏱️ ชั่วโมงกิจกรรม</a>
        <?php else: ?>
            <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
            <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <?php endif; ?>
        <a href="notifications.php">
            🔔 แจ้งเตือน<?= $unreadCount > 0
                ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?>
        </a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
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
            <form method="POST" enctype="multipart/form-data">
                <div class="avatar-upload-wrap">
                    <img src="<?= $avatarSrc ?>" alt="avatar" id="avatarPreview">
                    <br>
                    <label class="upload-btn" for="avatarInput">เปลี่ยนรูป</label>
                    <input type="file" name="avatar" id="avatarInput"
                           accept="image/jpeg,image/png,image/webp"
                           onchange="this.form.submit()">
                </div>
                <div class="avatar-hint">รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 2MB</div>
            </form>
        </div>

        <!-- ข้อมูลส่วนตัว -->
        <div class="card profile-card">
            <h2>แก้ไขข้อมูลส่วนตัว</h2>
            <form method="POST" class="profile-form">
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

</body>
</html>