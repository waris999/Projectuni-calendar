<?php
require_once __DIR__ . '/../src/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/RegistrationAvatar_Encoded.php';

// Redirect เมื่อเข้าสู่ระบบอยู่แล้ว
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php', true, 302);
    exit;
}

// ป้องกัน XSS
function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// ตรวจสอบค่าจากฟอร์ม
function postString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

// คำนวณชั้นปี
function calculateStudentYear(string $studentId): ?int
{
    if (!preg_match('/^\d{13}$/D', $studentId)) {
        return null;
    }

    $prefix = substr($studentId, 0, 2);
    $admissionYear = 2500 + (int) $prefix;
    $currentYear = (int) currentAcademicYear();

    $year = $currentYear - $admissionYear + 1;

    if ($year < 1 || $year > 8) {
        return null;
    }

    return $year;
}

$error = '';
$success = false;

$username = '';
$fullname = '';
$nameTitle = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // การตรวจสอบ CSRF ต้องยังทำงานผ่าน security.php
    // ตามระบบเดิมของเว็บไซต์

    $username = postString('username');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullname = postString('fullname');
    $nameTitle = postString('name_title');

    if (!is_string($password) ||
        !is_string($confirmPassword)) {
        $error = 'ข้อมูลรหัสผ่านไม่ถูกต้อง';
    }

    if ($error === '' &&
        !in_array(
            $nameTitle,
            ['นาย', 'นางสาว', 'นาง'],
            true
        )) {
        $error = 'กรุณาเลือกคำนำหน้าชื่อ';
    }

    elseif ($error === '' &&
        ($username === '' ||
         $password === '' ||
         $fullname === '')) {

        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    }

    elseif ($error === '' &&
        !preg_match('/^\d{13}$/D', $username)) {

        $error = 'รหัสนักศึกษาต้องเป็นตัวเลข 13 หลัก';
    }

    elseif ($error === '' &&
        calculateStudentYear($username) === null) {

        $error = 'รหัสนักศึกษาไม่ตรงกับรุ่นที่รับ';
    }

    elseif ($error === '' &&
        strlen($password) < 6) {

        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    }

    elseif ($error === '' &&
        $password !== $confirmPassword) {

        $error = 'รหัสผ่านไม่ตรงกัน';
    }

    else if ($error === '') {

        $studentYear = calculateStudentYear($username);

        $avatar = $_FILES['avatar'] ?? null;

        // ตรวจสอบข้อมูลไฟล์
        if ($avatar !== null) {

            if (!is_array($avatar) ||
                !isset($avatar['error']) ||
                !is_int($avatar['error'])) {

                $error = 'ข้อมูลรูปโปรไฟล์ไม่ถูกต้อง';
            }

            elseif ($avatar['error'] !== UPLOAD_ERR_OK &&
                    $avatar['error'] !== UPLOAD_ERR_NO_FILE) {

                error_log(
                    'Avatar upload error: ' .
                    $avatar['error']
                );

                $error = 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่';
            }
        }

        if ($error === '') {

            try {

                $userModel = new User($pdo);

                $ok = registerStudentWithEncodedAvatar(
                    $userModel,
                    $pdo,
                    $username,
                    $password,
                    $nameTitle . $fullname,
                    $studentYear,
                    avatarDataFromPost()
                );

                if ($ok) {
                    $success = true;
                } else {
                    $error = 'รหัสนักศึกษานี้ถูกใช้งานแล้ว หรือสมัครไม่สำเร็จ';
                }

            } catch (InvalidArgumentException $ex) {

                $error = $ex->getMessage();

            } catch (Throwable $ex) {

                error_log(
                    'Registration failed: ' .
                    get_class($ex) . ': ' .
                    $ex->getMessage()
                );

                $error = 'ระบบสมัครสมาชิกมีปัญหา กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>สมัครสมาชิก | Uni Calendar</title>

<link rel="stylesheet"
      href="css/style.css">

<link rel="stylesheet"
      href="css/registration-avatar.css">

<style>
* {
    box-sizing: border-box;
}

body.auth-page {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #1e5631;
    font-family: Arial, sans-serif;
}

.auth-box {
    background: #ffffff;
    padding: 32px;
    border-radius: 10px;
    width: min(360px, 100%);
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
}

.field-hint {
    font-size: 12px;
    color: #888;
    margin-bottom: 14px;
}

.auth-box .btn {
    width: 100%;
    padding: 12px;
    background: #1e5631;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.auth-box .btn:hover {
    background: #174628;
}

.auth-error {
    background: #fdecea;
    color: #b71c1c;
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 14px;
}

.auth-success {
    background: #e6f4ea;
    color: #1e5631;
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 14px;
}

.login-link {
    display: block;
    text-align: center;
    margin-top: 14px;
    font-size: 13px;
    color: #1e5631;
    text-decoration: none;
}
</style>
</head>

<body class="auth-page">

<div class="auth-box">

<h1>สมัครสมาชิก Uni Calendar</h1>

<?php if ($success): ?>

<div class="auth-success">
    สมัครสมาชิกสำเร็จแล้ว!
    <a href="index.php">
        คลิกที่นี่เพื่อเข้าสู่ระบบ
    </a>
</div>

<?php else: ?>

<?php if ($error !== ''): ?>
<div class="auth-error">
    <?= e($error) ?>
</div>
<?php endif; ?>

<form method="POST"
      action="register.php"
      enctype="application/x-www-form-urlencoded">

<?= csrfField() ?>

<?php
require __DIR__ .
    '/../src/registration-avatar-field.php';
?>

<div class="field">
<label for="name-title">คำนำหน้าชื่อ</label>

<select id="name-title"
        name="name_title"
        required>

<option value="">เลือกคำนำหน้าชื่อ</option>

<?php foreach (['นาย', 'นางสาว', 'นาง'] as $title): ?>

<option value="<?= e($title) ?>"
<?= $nameTitle === $title ? 'selected' : '' ?>>
<?= e($title) ?>
</option>

<?php endforeach; ?>

</select>
</div>

<label for="fullname">
ชื่อ-นามสกุล (ไม่ต้องใส่คำนำหน้า)
</label>

<input type="text"
       id="fullname"
       name="fullname"
       value="<?= e($fullname) ?>"
       required>

<div class="field-hint">&nbsp;</div>

<label for="username">
รหัสนักศึกษา (13 หลัก)
</label>

<input type="text"
       id="username"
       name="username"
       inputmode="numeric"
       pattern="[0-9]{13}"
       maxlength="13"
       minlength="13"
       value="<?= e($username) ?>"
       placeholder="เช่น 6612345678901"
       required>

<div class="field-hint">
ระบบจะคำนวณชั้นปีให้อัตโนมัติจากรหัสนักศึกษา
</div>

<label for="password">
รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)
</label>

<input type="password"
       id="password"
       name="password"
       minlength="6"
       required>

<div class="field-hint">&nbsp;</div>

<label for="confirm_password">
ยืนยันรหัสผ่าน
</label>

<input type="password"
       id="confirm_password"
       name="confirm_password"
       required>

<div class="field-hint">&nbsp;</div>

<button type="submit" class="btn">
สมัครสมาชิก
</button>

</form>

<?php endif; ?>

<a href="index.php" class="login-link">
มีบัญชีแล้ว? กลับไปเข้าสู่ระบบ
</a>

</div>

<script>
const usernameInput =
    document.getElementById('username');

if (usernameInput) {
    usernameInput.addEventListener(
        'input',
        function () {
            this.value = this.value
                .replace(/[^0-9]/g, '')
                .slice(0, 13);
        }
    );
}
</script>

<script src="js/registration-avatar.js?v=<?= filemtime(__DIR__ . '/js/registration-avatar.js') ?>"></script>

</body>
</html>
