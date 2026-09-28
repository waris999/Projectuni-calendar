<?php
session_start();
// ถ้า login อยู่แล้ว เด้งไป dashboard เลย
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
$openModal = $error !== '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Uni Calendar | ระบบปฏิทินกิจกรรมมหาวิทยาลัย</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="landing-navbar">
    <div class="brand">
        <img src="img/logo-placeholder.png" alt="logo">
        <span>Uni Calendar</span>
    </div>
    <button class="login-btn" onclick="openLoginModal()">Login</button>
</div>

<div class="hero">
    <h1>ระบบปฏิทินกิจกรรมมหาวิทยาลัย สำหรับนักศึกษาและเจ้าหน้าที่</h1>
    <button class="btn-hero" onclick="openLoginModal()">Login</button>
</div>

<div class="stats">
    <div class="stat-box">
        <div class="num">0</div>
        <div class="label">กิจกรรมทั้งหมด</div>
    </div>
    <div class="stat-box">
        <div class="num">0</div>
        <div class="label">การลงทะเบียน</div>
    </div>
    <div class="stat-box">
        <div class="num">0</div>
        <div class="label">ผู้ใช้งาน</div>
    </div>
</div>

<!-- ===== Login Modal ===== -->
<div class="modal-overlay <?= $openModal ? 'active' : '' ?>" id="loginModal">
    <div class="modal-box">
        <button class="close-btn" onclick="closeLoginModal()">&times;</button>
        <h2>ลงชื่อเข้าใช้งาน</h2>

        <?php if ($error === 'invalid'): ?>
            <div class="modal-error">Username หรือ Password ไม่ถูกต้อง</div>
        <?php elseif ($error === 'empty'): ?>
            <div class="modal-error">กรุณากรอกข้อมูลให้ครบ</div>
        <?php elseif ($error === 'locked'): ?>
            <div class="modal-error">บัญชีถูกล็อกชั่วคราวเนื่องจากพยายามเข้าสู่ระบบผิดหลายครั้ง กรุณาลองใหม่ใน 15 นาที</div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" class="btn-submit">เข้าใช้งาน</button>
            <p style="text-align:center; margin-top:12px; font-size:13px;">
    ยังไม่มีบัญชี? <a href="register.php" style="color:#1e5631; font-weight:600;">สมัครสมาชิก</a>
</p>
        </form>                                        
    </div>
</div>

<script>
function openLoginModal() {
    document.getElementById('loginModal').classList.add('active');
}
function closeLoginModal() {
    document.getElementById('loginModal').classList.remove('active');
}
</script>

</body>
</html>