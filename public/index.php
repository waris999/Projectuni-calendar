<?php
require_once __DIR__ . '/../src/security.php';
if (session_status() === PHP_SESSION_NONE) {
    /* session initialized by security.php */
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';
require_once __DIR__ . '/../src/RegistrationAvatar.php';
require_once __DIR__ . '/../src/RegistrationAvatar_Encoded.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$loginError    = '';
$registerError = '';
$registerSuccess = false;

// ── Login ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $loginError = 'กรุณากรอก Username และรหัสผ่าน';
    } else {
        $userModel = new User($pdo);
        $result = $userModel->login($username, $password);

        if ($result === 'locked') {
            $loginError = 'บัญชีถูกระงับชั่วคราว กรุณารอ 15 นาทีแล้วลองใหม่';
        } elseif ($result === 'invalid') {
            $loginError = 'Username หรือรหัสผ่านไม่ถูกต้อง';
        } elseif (is_array($result)) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $result['id'];
            $_SESSION['username']      = $result['username'];
            $_SESSION['fullname']      = $result['fullname'];
            $_SESSION['role']          = $result['role'];
            $_SESSION['student_year']  = $result['student_year'];
            $_SESSION['profile_image'] = $result['profile_image'] ?? '';
            header('Location: dashboard.php');
            exit;
        } else {
            $loginError = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    }
}

// ── Register ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'register') {
    $username        = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullname        = trim($_POST['fullname'] ?? '');
    $nameTitle = is_string($_POST['name_title'] ?? null) ? $_POST['name_title'] : '';

    // คำนวณชั้นปีอัตโนมัติจาก 2 ตัวแรกของรหัสนักศึกษา
    // รหัสปี พ.ศ. สั้น: 66=ปี4, 67=ปี3, 68=ปี2, 69=ปี1 (ปีปัจจุบัน 2569)
    // รับชั้นปีที่ 1-8 (กรณีเรียนเกินกำหนด)
    $currentShortYear = (int)currentAcademicYear() % 100; // เช่น 2569 → 69
    $entryShortYear   = strlen($username) >= 2 ? (int)substr($username, 0, 2) : 0;
    $studentYear      = $currentShortYear - $entryShortYear + 1;
    if ($studentYear < 1 || $studentYear > 8) {
        $studentYear = 0; // รหัสไม่ตรงกับรุ่นที่รับ
    }

    if (!in_array($nameTitle, ['นาย', 'นางสาว', 'นาง'], true)) {
        $registerError = 'กรุณาเลือกคำนำหน้าชื่อ';
    } elseif ($username === '' || $password === '' || $fullname === '') {
        $registerError = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif (strlen($username) !== 13 || !ctype_digit($username)) {
        $registerError = 'รหัสนักศึกษาต้องเป็นตัวเลข 13 หลัก';
    } elseif ($studentYear < 1 || $studentYear > 8) {
        $registerError = 'รหัสนักศึกษาไม่ตรงกับปีการศึกษาปัจจุบัน (รับรุ่นย้อนหลังไม่เกิน 8 ปี)';
    } elseif (strlen($password) < 6) {
        $registerError = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($password !== $confirmPassword) {
        $registerError = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $userModel = new User($pdo);
        $ok = false;
        try {
            $ok = registerStudentWithEncodedAvatar($userModel, $pdo, $username, $password, $nameTitle . $fullname, $studentYear, avatarDataFromPost());
        } catch (InvalidArgumentException $e) {
            $registerError = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Registration failed: ' . get_class($e));
            $registerError = 'สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่';
        }
        if ($ok) {
            $registerSuccess = true;
        } else {
            if ($registerError === '') $registerError = 'รหัสนักศึกษานี้ถูกใช้งานแล้ว';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Uni Calendar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Sarabun:wght@300;400;500;600&display=swap">
<style>
/* ─── Tokens ─────────────────────────────────────────────── */
:root {
--forest:     #1a4a2e;
--leaf:       #2d7a4f;
--mint:       #a8d5b5;
--cream:      #f7f9f5;
--ink:        #1c2b22;
--muted:      #6b7f72;
--border:     #d4e3d9;
--error:      #c0392b;
--error-bg:   #fef2f1;
--success-bg: #edf7f1;
--success:    #1a4a2e;
--white:      #ffffff;
--font-display: 'DM Serif Display', Georgia, serif;
--font-body:    'Sarabun', system-ui, sans-serif;
}
@media (prefers-color-scheme: dark) {
:root:not([data-theme="light"]) {
    --forest: #2d7a4f; --leaf: #3d9c65; --mint: #3d9c65;
    --cream:  #111e16; --ink:  #e8f2eb; --muted: #8aab95;
    --border: #2a3f31; --white: #182419;
    color-scheme: dark;
}
}
:root[data-theme="dark"] {
--forest: #2d7a4f; --leaf: #3d9c65; --mint: #3d9c65;
--cream:  #111e16; --ink:  #e8f2eb; --muted: #8aab95;
--border: #2a3f31; --white: #182419;
color-scheme: dark;
}

/* ─── Reset ──────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
font-family: var(--font-body);
background: var(--cream);
color: var(--ink);
min-height: 100vh;
display: flex;
align-items: stretch;
}

/* ─── Layout ─────────────────────────────────────────────── */
.split { display: flex; width: 100%; min-height: 100vh; }

/* LEFT */
.hero {
flex: 1 1 45%;
background: var(--forest);
position: relative;
display: flex;
flex-direction: column;
justify-content: flex-end;
padding: 56px 52px;
overflow: hidden;
min-height: 420px;
}
.hero::before {
content: '';
position: absolute; inset: 0;
background-image:
    radial-gradient(circle at 80% 20%, rgba(45,122,79,0.55) 0%, transparent 55%),
    radial-gradient(circle at 15% 75%, rgba(168,213,181,0.12) 0%, transparent 45%);
pointer-events: none;
}
.hero-pattern {
position: absolute; inset: 0; opacity: 0.07; pointer-events: none;
}
.hero-eyebrow {
font-size: 12px; font-weight: 500; letter-spacing: 0.12em;
color: var(--mint); text-transform: uppercase;
margin-bottom: 16px; position: relative;
}
.hero-title {
font-family: var(--font-display);
font-size: clamp(32px, 4vw, 52px);
line-height: 1.12; color: #fff;
position: relative; margin-bottom: 20px; text-wrap: balance;
}
.hero-sub {
font-size: 15px; font-weight: 300; line-height: 1.7;
color: rgba(255,255,255,0.68); position: relative; max-width: 340px;
}
.hero-stats { display: flex; gap: 36px; margin-top: 44px; position: relative; }
.stat-item strong {
display: block; font-family: var(--font-display);
font-size: 28px; color: #fff; line-height: 1;
}
.stat-item span {
font-size: 12px; color: rgba(255,255,255,0.55);
font-weight: 400; margin-top: 4px; display: block;
}

/* RIGHT */
.panel {
flex: 1 1 55%;
display: flex; align-items: center; justify-content: center;
padding: 48px 40px; background: var(--white);
}
.form-wrap { width: 100%; max-width: 400px; }

.form-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
.form-logo-text { font-weight: 600; font-size: 15px; color: var(--forest); line-height: 1.3; }
.form-logo-text small { display: block; font-weight: 400; font-size: 12px; color: var(--muted); }

.form-heading {
font-family: var(--font-display); font-size: 30px;
color: var(--ink); margin-bottom: 6px; line-height: 1.15;
}
.form-sub { font-size: 14px; color: var(--muted); margin-bottom: 32px; font-weight: 300; }

/* Tabs */
.tabs { display: flex; border-bottom: 2px solid var(--border); margin-bottom: 28px; }
.tab-btn {
background: none; border: none; padding: 10px 0; margin-right: 28px;
font-family: var(--font-body); font-size: 14px; font-weight: 500;
color: var(--muted); cursor: pointer; position: relative; transition: color 0.18s;
}
.tab-btn.active { color: var(--forest); }
.tab-btn.active::after {
content: ''; position: absolute; left: 0; right: 0; bottom: -2px;
height: 2px; background: var(--forest); border-radius: 2px 2px 0 0;
}

/* Fields */
.field { margin-bottom: 18px; }
.field label { display: block; font-size: 13px; font-weight: 500; color: var(--ink); margin-bottom: 7px; }
.field input, .field select {
width: 100%; padding: 11px 14px;
border: 1.5px solid var(--border); border-radius: 8px;
font-family: var(--font-body); font-size: 14px;
color: var(--ink); background: var(--cream);
transition: border-color 0.15s, box-shadow 0.15s;
outline: none; appearance: none;
}
.field input:focus, .field select:focus {
border-color: var(--leaf);
box-shadow: 0 0 0 3px rgba(45,122,79,0.12);
background: var(--white);
}
.field input::placeholder { color: var(--muted); }
.select-wrap { position: relative; }
.select-wrap::after {
content: ''; pointer-events: none; position: absolute;
right: 14px; top: 50%; transform: translateY(-50%);
width: 0; height: 0;
border-left: 5px solid transparent; border-right: 5px solid transparent;
border-top: 6px solid var(--muted);
}

/* Button */
.btn-primary {
width: 100%; padding: 13px; background: var(--forest); color: #fff;
border: none; border-radius: 8px; font-family: var(--font-body);
font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 8px;
transition: background 0.18s, transform 0.1s;
}
.btn-primary:hover  { background: var(--leaf); }
.btn-primary:active { transform: scale(0.99); }
.btn-primary:focus-visible { outline: 3px solid var(--mint); outline-offset: 2px; }

/* Alerts */
.alert { padding: 11px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; line-height: 1.5; }
.alert-error   { background: var(--error-bg);   color: var(--error);   }
.alert-success { background: var(--success-bg); color: var(--success); font-weight: 500; }

/* Lock note */
.lock-info {
font-size: 12px; color: var(--muted); margin-top: 18px;
text-align: center; display: flex; align-items: center;
justify-content: center; gap: 6px;
}

/* Panels */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* Responsive */
@media (max-width: 720px) {
.split { flex-direction: column; }
.hero { flex: none; min-height: 200px; padding: 36px 24px 32px; justify-content: flex-start; }
.hero-stats { display: none; }
.hero-title { font-size: 28px; }
.panel { padding: 32px 20px 48px; }
.form-wrap { max-width: 100%; }
}
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
</style>
<link rel="stylesheet" href="css/registration-avatar.css?v=<?= filemtime(__DIR__ . '/css/registration-avatar.css') ?>">
</head>
<body>

<div class="split">

<!-- LEFT -->
<div class="hero">
    <svg class="hero-pattern" viewBox="0 0 600 700" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="500" cy="80"  r="220" stroke="white" stroke-width="1.5"/>
    <circle cx="500" cy="80"  r="160" stroke="white" stroke-width="1"/>
    <circle cx="60"  cy="600" r="180" stroke="white" stroke-width="1.5"/>
    <line x1="0" y1="200" x2="600" y2="200" stroke="white" stroke-width="0.8"/>
    <line x1="0" y1="400" x2="600" y2="400" stroke="white" stroke-width="0.8"/>
    <line x1="150" y1="0" x2="150" y2="700" stroke="white" stroke-width="0.8"/>
    <line x1="400" y1="0" x2="400" y2="700" stroke="white" stroke-width="0.8"/>
    <rect x="120" y="170" width="60" height="60" stroke="white" stroke-width="1.2" transform="rotate(45 150 200)"/>
    <rect x="360" y="360" width="80" height="80" stroke="white" stroke-width="1"   transform="rotate(15 400 400)"/>
    </svg>

    <p class="hero-eyebrow">ระบบจัดการกิจกรรม</p>
    <h1 class="hero-title">ปฏิทินกิจกรรม<br>มหาวิทยาลัยราชภัฏ<br>พระนคร</h1>
    <p class="hero-sub">ค้นหา ลงทะเบียน และติดตามกิจกรรมที่คุณสนใจ พร้อมบันทึกชั่วโมงสะสมในที่เดียว</p>
</div>

<!-- RIGHT -->
<div class="panel">
    <div class="form-wrap">

    <div class="form-logo">
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="36" height="36" rx="9" fill="#1a4a2e"/>
        <path d="M8 24l10-13 10 13" stroke="#a8d5b5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M13 24v-5h10v5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="16" y="20" width="4" height="4" rx="0.5" fill="#a8d5b5"/>
        </svg>
        <div class="form-logo-text">
        Uni Calendar
        <small>ระบบปฏิทินกิจกรรม</small>
        </div>
    </div>

    <h2 class="form-heading">ยินดีต้อนรับ</h2>
    <p class="form-sub">เข้าสู่ระบบหรือสมัครสมาชิกเพื่อเริ่มต้น</p>

    <div class="tabs" role="tablist">
        <button class="tab-btn active" role="tab" aria-selected="true"
                onclick="switchTab('login',this)">เข้าสู่ระบบ</button>
        <button class="tab-btn" role="tab" aria-selected="false"
                onclick="switchTab('register',this)">สมัครสมาชิก</button>
    </div>

    <!-- LOGIN -->
    <div id="tab-login" class="tab-panel active">
        <?php if ($loginError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="POST">
<?= csrfField() ?>
        <input type="hidden" name="form_type" value="login">
        <div class="field">
            <label for="login_user">รหัสนักศึกษา / Username</label>
            <input type="text" id="login_user" name="username" autocomplete="username" required
                placeholder="กรอกรหัสนักศึกษา"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="login_pass">รหัสผ่าน</label>
            <input type="password" id="login_pass" name="password"
                placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-primary">เข้าสู่ระบบ</button>
        </form>
        <p class="lock-info">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        ล็อกอินผิด 5 ครั้งจะถูกระงับ 15 นาที
        </p>
    </div>

    <!-- REGISTER -->
    <div id="tab-register" class="tab-panel">
        <?php if ($registerError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($registerError) ?></div>
        <?php endif; ?>
        <?php if ($registerSuccess): ?>
        <div class="alert alert-success">✓ สมัครสมาชิกสำเร็จแล้ว! เข้าสู่ระบบได้เลย</div>
        <?php endif; ?>
        <form method="POST" action="index.php" enctype="application/x-www-form-urlencoded">
<?= csrfField() ?>
        <input type="hidden" name="form_type" value="register">
        <?php require __DIR__ . '/../src/registration-avatar-field.php'; ?>
<div class="field">
            <label for="name-title">คำนำหน้าชื่อ</label>
            <select id="name-title" name="name_title" required>
                <option value="">เลือกคำนำหน้าชื่อ</option>
                <?php foreach (['นาย', 'นางสาว', 'นาง'] as $title): ?>
                <option value="<?= $title ?>" <?= ($_POST['name_title'] ?? '') === $title ? 'selected' : '' ?>><?= $title ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="reg_name">ชื่อ-นามสกุล (ไม่ต้องใส่คำนำหน้า)</label>
            <input type="text" id="reg_name" name="fullname" required
                placeholder="เช่น สมชาย ใจดี"
                value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="reg_user">รหัสนักศึกษา (13 หลัก)</label>
            <input type="text" id="reg_user" name="username" maxlength="13" pattern="\d{13}" required
                placeholder="6XXXXXXXXX01"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                oninput="detectYear(this.value)">
            <div id="year-badge" style="margin-top:8px; font-size:13px; color:var(--muted);"></div>
        </div>
        <div class="field">
            <label for="reg_pass">รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)</label>
            <input type="password" id="reg_pass" name="password"
                placeholder="••••••••" required>
        </div>
        <div class="field">
            <label for="reg_confirm">ยืนยันรหัสผ่าน</label>
            <input type="password" id="reg_confirm" name="confirm_password"
                placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-primary">สมัครสมาชิก</button>
        </form>
    </div>

    </div>
</div>
</div>

<script>
const CURRENT_SHORT_YEAR = <?= (int)currentAcademicYear() % 100 ?>; // เช่น 69

function detectYear(val) {
    const badge = document.getElementById('year-badge');
    if (val.length < 2) { badge.innerHTML = ''; return; }
    const entry = parseInt(val.substring(0, 2), 10);
    const year  = CURRENT_SHORT_YEAR - entry + 1;
    if (year >= 1 && year <= 4) {
        badge.innerHTML = `<span style="display:inline-flex;align-items:center;gap:6px;background:#edf7f1;color:#1a4a2e;padding:4px 12px;border-radius:20px;font-weight:500;">🎓 ชั้นปีที่ ${year}</span>`;
    } else if (year >= 5 && year <= 8) {
        badge.innerHTML = `<span style="display:inline-flex;align-items:center;gap:6px;background:#fff8e1;color:#b7791f;padding:4px 12px;border-radius:20px;font-weight:500;">📚 ชั้นปีที่ ${year} (เรียนต่อเนื่อง)</span>`;
    } else {
        badge.innerHTML = `<span style="display:inline-flex;align-items:center;gap:6px;background:#fef2f1;color:#c0392b;padding:4px 12px;border-radius:20px;">⚠️ รหัสปี ${entry} ไม่อยู่ในรุ่นที่เปิดรับ</span>`;
    }
}

// เรียกครั้งแรกถ้ามีค่าจาก PHP (กรณี error แล้ว reload)
document.addEventListener('DOMContentLoaded', () => {
    const u = document.getElementById('reg_user');
    if (u && u.value) detectYear(u.value);
});

function switchTab(tab, el) {
document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.remove('active');
    b.setAttribute('aria-selected', 'false');
});
document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
el.classList.add('active');
el.setAttribute('aria-selected', 'true');
document.getElementById('tab-' + tab).classList.add('active');
}
<?php if ($registerError || $registerSuccess): ?>
document.addEventListener('DOMContentLoaded', () =>
switchTab('register', document.querySelectorAll('.tab-btn')[1])
);
<?php endif; ?>
</script>

<script src="js/registration-avatar.js?v=<?= filemtime(__DIR__ . '/js/registration-avatar.js') ?>"></script>
</body>
</html>
