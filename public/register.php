<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';

// ถ้า login อยู่แล้ว ไม่ต้องมาหน้าสมัครอีก
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// คำนวณชั้นปีจากรหัสนักศึกษา 2 ตัวแรก (เช่น 66xxxxxxxxxxx = เข้าปี พ.ศ. 2566)
function calculateStudentYear(string $studentId): ?int
{
    $prefix = substr($studentId, 0, 2);
    if (!ctype_digit($prefix)) {
        return null;
    }
    $admissionYear = 2500 + (int) $prefix;       // เช่น 66 -> 2566
    $currentYear   = (int) date('Y') + 543;      // แปลงปี ค.ศ. ปัจจุบันเป็น พ.ศ.
    $year = $currentYear - $admissionYear + 1;

    if ($year < 1) {
        $year = 1; // กันกรณีรหัสของปีนี้เอง ให้เริ่มที่ปี 1
    }
    return $year;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullname        = trim($_POST['fullname'] ?? '');
    $role            = 'student'; // บังคับให้สมัครเป็นนักศึกษาเท่านั้น admin ต้องสร้างผ่าน phpMyAdmin

    if ($username === '' || $password === '' || $fullname === '') {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif (!preg_match('/^\d{13}$/', $username)) {
        $error = 'รหัสนักศึกษาต้องเป็นตัวเลข 13 หลักเท่านั้น';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($password !== $confirmPassword) {
        $error = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $studentYear = calculateStudentYear($username);

        $userModel = new User($pdo);
        $ok = $userModel->register($username, $password, $fullname, $role, $studentYear);

        if ($ok) {
            $success = true;
        } else {
            $error = 'รหัสนักศึกษานี้ถูกใช้งานแล้ว';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>สมัครสมาชิก | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<style>
    body.auth-page {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #1e5631;
        min-height: 100vh;
    }
    .auth-box {
        background: #fff;
        padding: 32px;
        border-radius: 10px;
        width: 360px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .auth-box h1 {
        font-size: 18px;
        color: #1e5631;
        margin-bottom: 20px;
        text-align: center;
    }
    .auth-box label {
        display: block;
        font-size: 13px;
        color: #444;
        margin-bottom: 4px;
    }
    .auth-box input,
    .auth-box select {
        width: 100%;
        padding: 10px;
        margin-bottom: 6px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .field-hint {
        font-size: 12px;
        color: #888;
        margin-bottom: 14px;
    }
    .auth-box .btn {
        width: 100%;
        text-align: center;
        border: none;
        cursor: pointer;
    }
    .auth-error {
        background: #fdecea;
        color: #b71c1c;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        margin-bottom: 14px;
    }
    .auth-success {
        background: #e6f4ea;
        color: #1e5631;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        margin-bottom: 14px;
    }
    .auth-box .login-link {
        display: block;
        text-align: center;
        margin-top: 14px;
        font-size: 13px;
        color: #1e5631;
    }
</style>
</head>
<body class="auth-page">

<div class="auth-box">
    <h1>สมัครสมาชิก Uni Calendar</h1>

    <?php if ($success): ?>
        <div class="auth-success">
            สมัครสมาชิกสำเร็จแล้ว!
            <a href="index.php" style="color:#1e5631; font-weight:600;">คลิกที่นี่เพื่อเข้าสู่ระบบ</a>
        </div>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <label for="fullname">ชื่อ-นามสกุล</label>
            <input type="text" id="fullname" name="fullname"
                value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
            <div class="field-hint">&nbsp;</div>

            <label for="username">รหัสนักศึกษา (13 หลัก)</label>
            <input type="text" id="username" name="username"
                inputmode="numeric" pattern="[0-9]{13}" maxlength="13" minlength="13"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                placeholder="เช่น 6612345678901" required>
            <div class="field-hint">ระบบจะคำนวณชั้นปีให้อัตโนมัติจากรหัสนักศึกษา</div>

            <label for="password">รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)</label>
            <input type="password" id="password" name="password" required>
            <div class="field-hint">&nbsp;</div>

            <label for="confirm_password">ยืนยันรหัสผ่าน</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <div class="field-hint">&nbsp;</div>

            <button type="submit" class="btn">สมัครสมาชิก</button>
        </form>

    <?php endif; ?>

    <a href="index.php" class="login-link">มีบัญชีแล้ว? กลับไปเข้าสู่ระบบ</a>
</div>

<script>
    // อนุญาตให้พิมพ์ได้เฉพาะตัวเลข และไม่เกิน 13 หลัก
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);
        });
    }
</script>

</body>
</html>