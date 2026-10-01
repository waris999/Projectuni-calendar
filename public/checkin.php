<?php
session_start();
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
$myEvents   = $eventModel->getRegisteredByUserForCheckin($_SESSION['user_id']);
$unreadCount = $notifModel->countUnread($_SESSION['user_id']);

$autoEventId = (int)($_GET['auto_event'] ?? 0);
$autoCode    = trim($_GET['auto_code']   ?? '');

$uploadSuccess = '';
$uploadError   = '';

// ===== อัปโหลดรูปหลักฐาน =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['evidence_photo'])) {
    $uploadEventId = (int)($_POST['upload_event_id'] ?? 0);

    // เช็คว่ากิจกรรมยังไม่ผ่านไปแล้ว
    $evStmt = $pdo->prepare("SELECT end_datetime FROM events WHERE id = :id");
    $evStmt->execute(['id' => $uploadEventId]);
    $evRow = $evStmt->fetch();

    if (!$evRow || strtotime($evRow['end_datetime']) < time()) {
        $uploadError = 'ไม่สามารถอัปโหลดรูปสำหรับกิจกรรมที่ผ่านไปแล้วได้';
    } else {
        // เช็คว่า user เช็คอินกิจกรรมนี้แล้วจริง
        $regStmt = $pdo->prepare(
            "SELECT status FROM registrations WHERE user_id = :uid AND event_id = :eid LIMIT 1"
        );
        $regStmt->execute(['uid' => $_SESSION['user_id'], 'eid' => $uploadEventId]);
        $reg = $regStmt->fetch();

        if (!$reg || $reg['status'] !== 'attended') {
            $uploadError = 'ต้องเช็คอินก่อนจึงจะอัปโหลดรูปได้';
        } else {
            $allowedTypes  = ['image/jpeg', 'image/png', 'image/webp'];
            $caption       = trim($_POST['caption'] ?? '');
            $files         = $_FILES['evidence_photo'];
            $total         = count($files['name']);
            $uploadedCount = 0;
            $errors        = [];

            for ($i = 0; $i < $total; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

                $fileType = mime_content_type($files['tmp_name'][$i]);
                if (!in_array($fileType, $allowedTypes, true)) {
                    $errors[] = $files['name'][$i] . ': ไม่รองรับประเภทไฟล์นี้';
                    continue;
                }
                if ($files['size'][$i] > 5 * 1024 * 1024) {
                    $errors[] = $files['name'][$i] . ': ขนาดเกิน 5MB';
                    continue;
                }

                $ext         = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $newFilename = 'ep_' . $_SESSION['user_id'] . '_' . $uploadEventId . '_' . time() . '_' . $i . '.' . $ext;
                $dest        = __DIR__ . '/img/event_photos/' . $newFilename;

                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $photoModel->addPhoto($_SESSION['user_id'], $uploadEventId, $newFilename, $caption);
                    $uploadedCount++;
                }
            }

            if ($uploadedCount > 0) {
                $uploadSuccess = "อัปโหลดรูปหลักฐานสำเร็จ {$uploadedCount} รูป";
            }
            if (!empty($errors)) {
                $uploadError = implode(', ', $errors);
            }
        }
    }
}

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>เช็คอินกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<style>
    .checkin-item {
        padding: 16px 0;
        border-bottom: 1px solid #eee;
    }
    .checkin-item:last-child { border-bottom: none; }

    .checkin-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        flex-wrap: wrap;
    }
    .checkin-top .info strong { display: block; margin-bottom: 4px; }
    .checkin-top .info span { font-size: 12px; color: #666; }

    .badge { font-size: 12px; padding: 3px 10px; border-radius: 12px; white-space: nowrap; }
    .badge-attended  { background: #e6f4ea; color: #1e5631; }
    .badge-pending   { background: #fff4e5; color: #b8860b; }

    .checkin-form {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .checkin-form input {
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 13px;
        width: 130px;
    }
    .checkin-form button {
        background: #1e5631;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
    }
    .checkin-msg { font-size: 12px; margin-top: 6px; }

    /* อัปโหลดรูปหลักฐาน */
    .evidence-section {
        margin-top: 14px;
        background: #f8fdf9;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        padding: 14px;
    }
    .evidence-section h4 {
        font-size: 13px;
        color: #1e5631;
        margin-bottom: 10px;
    }
    .evidence-upload {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .evidence-upload label.pick-btn {
        background: #fff;
        border: 1px solid #1e5631;
        color: #1e5631;
        padding: 7px 14px;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        white-space: nowrap;
    }
    .evidence-upload input[type="file"] { display: none; }
    .evidence-upload input[type="text"] {
        flex: 1;
        min-width: 160px;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 13px;
    }
    .evidence-upload button {
        background: #1e5631;
        color: #fff;
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        white-space: nowrap;
    }
    .evidence-thumbs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    .evidence-thumbs img {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 6px;
        border: 2px solid #c3e6cb;
        cursor: pointer;
    }
    .past-notice {
        font-size: 12px;
        color: #999;
        margin-top: 10px;
        font-style: italic;
    }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="avatar"></a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">
            นักศึกษา<?= !empty($_SESSION['student_year'])
                ? ' ชั้นปีที่ ' . (int)$_SESSION['student_year'] : '' ?>
        </div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="checkin.php" class="active">✅ เช็คอินกิจกรรม</a>
        <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <a href="notifications.php">🔔 แจ้งเตือน<?= $unreadCount > 0
            ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?></a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / เช็คอินกิจกรรม</div>
    <div class="content">

        <?php if ($uploadSuccess): ?>
            <div style="background:#e6f4ea; color:#1e5631; padding:8px 14px; border-radius:6px; margin-bottom:14px; font-size:13px;">
                <?= htmlspecialchars($uploadSuccess) ?>
            </div>
        <?php endif; ?>
        <?php if ($uploadError): ?>
            <div style="background:#fdecea; color:#b71c1c; padding:8px 14px; border-radius:6px; margin-bottom:14px; font-size:13px;">
                <?= htmlspecialchars($uploadError) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>ยืนยันการเข้าร่วมกิจกรรม</h2>
            <p style="font-size:13px; color:#666; margin-bottom:14px;">
                กรอกรหัสเช็คอินที่ได้รับหน้างาน แล้วอัปโหลดรูปหลักฐานประกอบได้เลย
            </p>
            <button class="btn-outline" id="openScannerBtn"
                    style="width:200px; margin-bottom:16px;">
                📷 สแกน QR Code
            </button>
            <div id="qrScannerBox" style="display:none; max-width:320px; margin-bottom:16px;"></div>

            <?php if (empty($myEvents)): ?>
                <p style="color:#999; font-size:14px;">คุณยังไม่ได้ลงทะเบียนกิจกรรมใดๆ</p>
            <?php else: ?>
                <?php foreach ($myEvents as $ev):
                    $isAttended = $ev['status'] === 'attended';
                    $hasStarted = strtotime($ev['start_datetime']) <= time();
                    $isPast     = strtotime($ev['end_datetime']) < time();

                    // ดึงรูปที่อัปโหลดไว้แล้ว (เฉพาะกิจกรรมที่เช็คอินแล้ว)
                    $existingPhotos = $isAttended
                        ? $photoModel->getPhotos($_SESSION['user_id'], $ev['id'])
                        : [];
                ?>
                    <div class="checkin-item">
                        <div class="checkin-top">
                            <div class="info">
                                <strong><?= htmlspecialchars($ev['title']) ?></strong>
                                <span>
                                    📍 <?= htmlspecialchars($ev['location']) ?> |
                                    🕒 <?= date('d/m/Y H:i', strtotime($ev['start_datetime'])) ?>
                                </span>
                            </div>

                            <?php if ($isAttended): ?>
                                <span class="badge badge-attended">เช็คอินแล้ว ✓</span>
                            <?php elseif (!$hasStarted): ?>
                                <span class="badge badge-pending">ยังไม่ถึงเวลากิจกรรม</span>
                            <?php else: ?>
                                <!-- ฟอร์มเช็คอิน -->
                                <div>
                                    <div class="checkin-form">
                                        <input type="text"
                                            placeholder="กรอกรหัส"
                                            class="checkin-code-input"
                                            maxlength="10">
                                        <button onclick="submitCheckin(<?= $ev['id'] ?>, this)">
                                            ยืนยัน
                                        </button>
                                    </div>
                                    <div class="checkin-msg"></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isAttended): ?>
                            <!-- ส่วนรูปหลักฐาน -->
                            <div class="evidence-section">
                                <h4>📸 รูปหลักฐานการเข้าร่วม</h4>

                                <?php if (!$isPast): ?>
                                    <!-- กิจกรรมยังไม่ผ่าน → อัปโหลดได้ -->
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="upload_event_id" value="<?= (int)$ev['id'] ?>">
                                        <div class="evidence-upload">
                                            <label class="pick-btn" for="photo_<?= $ev['id'] ?>">
                                                + เพิ่มรูป
                                            </label>
                                            <input type="file"
                                                name="evidence_photo[]"
                                                id="photo_<?= $ev['id'] ?>"
                                                accept="image/jpeg,image/png,image/webp"
                                                multiple
                                                onchange="previewSelected(this, <?= $ev['id'] ?>)">
                                            <input type="text" name="caption"
                                                placeholder="คำบรรยาย (ไม่บังคับ)">
                                            <button type="submit">อัปโหลด</button>
                                        </div>
                                        <div id="preview_<?= $ev['id'] ?>"
                                            style="display:flex; gap:6px; flex-wrap:wrap; margin-top:8px;"></div>
                                    </form>
                                <?php else: ?>
                                    <p class="past-notice">กิจกรรมสิ้นสุดแล้ว ไม่สามารถเพิ่มรูปได้</p>
                                <?php endif; ?>

                                <!-- รูปที่อัปโหลดไว้แล้ว -->
                                <?php if (!empty($existingPhotos)): ?>
                                    <div class="evidence-thumbs" style="margin-top:10px;">
                                        <?php foreach ($existingPhotos as $photo): ?>
                                            <img src="img/event_photos/<?= htmlspecialchars($photo['filename']) ?>"
                                                alt="<?= htmlspecialchars($photo['caption'] ?? '') ?>"
                                                title="<?= htmlspecialchars($photo['caption'] ?? 'รูปหลักฐาน') ?>"
                                                onclick="openFullImg('img/event_photos/<?= htmlspecialchars($photo['filename']) ?>')">
                                        <?php endforeach; ?>
                                    </div>
                                    <p style="font-size:12px; color:#888; margin-top:6px;">
                                        รูปทั้งหมด <?= count($existingPhotos) ?> รูป —
                                        <a href="event_album.php?event_id=<?= (int)$ev['id'] ?>"
                                        style="color:#1e5631;">ดูอัลบั้มเต็ม</a>
                                    </p>
                                <?php else: ?>
                                    <p style="font-size:12px; color:#aaa; margin-top:8px;">
                                        ยังไม่มีรูปหลักฐาน
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Lightbox รูปเต็ม -->
<div id="fullImgOverlay"
    onclick="this.style.display='none'; document.body.style.overflow='';"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.88);
        z-index:9999; align-items:center; justify-content:center;">
    <img id="fullImgEl" src=""
        style="max-width:92vw; max-height:88vh; border-radius:8px; object-fit:contain;">
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
// ===== เช็คอิน =====
async function submitCheckin(eventId, btnEl) {
    const wrap     = btnEl.closest('.checkin-item');
    const codeInput = wrap.querySelector('.checkin-code-input');
    const msgEl    = wrap.querySelector('.checkin-msg');
    const code     = codeInput.value.trim();

    if (!code) {
        msgEl.textContent = 'กรุณากรอกรหัส';
        msgEl.style.color = '#b71c1c';
        return;
    }

    msgEl.textContent = 'กำลังตรวจสอบ...';
    msgEl.style.color = '#666';

    try {
        const res = await fetch('api/checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: eventId, code: code })
        });
        const result = await res.json();

        if (result.result === 'success') {
            msgEl.textContent = 'เช็คอินสำเร็จ! 🎉';
            msgEl.style.color = '#1e5631';
            // reload หลัง 1 วินาที เพื่อให้เห็นฟอร์มอัปโหลดรูปทันที
            setTimeout(() => location.reload(), 1000);
        } else if (result.result === 'wrong_code') {
            msgEl.textContent = 'รหัสไม่ถูกต้อง';
            msgEl.style.color = '#b71c1c';
        } else if (result.result === 'not_started') {
            msgEl.textContent = 'กิจกรรมยังไม่เริ่ม';
            msgEl.style.color = '#b71c1c';
        } else if (result.result === 'already_checked_in') {
            msgEl.textContent = 'คุณเช็คอินไปแล้ว';
            msgEl.style.color = '#1e5631';
            setTimeout(() => location.reload(), 1000);
        } else {
            msgEl.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            msgEl.style.color = '#b71c1c';
        }
    } catch (err) {
        msgEl.textContent = 'เชื่อมต่อไม่สำเร็จ';
        msgEl.style.color = '#b71c1c';
    }
}

// ===== Preview รูปก่อนอัปโหลด =====
function previewSelected(input, eventId) {
    const container = document.getElementById('preview_' + eventId);
    container.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = 'width:60px;height:60px;object-fit:cover;border-radius:6px;border:2px solid #c3e6cb;';
            container.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// ===== เปิดรูปเต็มจอ =====
function openFullImg(src) {
    document.getElementById('fullImgEl').src = src;
    const overlay = document.getElementById('fullImgOverlay');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('fullImgOverlay').style.display = 'none';
        document.body.style.overflow = '';
    }
});

// ===== QR Scanner =====
document.getElementById('openScannerBtn').addEventListener('click', function () {
    const box = document.getElementById('qrScannerBox');
    if (box.style.display === 'none') {
        box.style.display = 'block';
        box.innerHTML = '<div id="qrReader" style="width:100%;"></div>';
        const scanner = new Html5Qrcode('qrReader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: 200 },
            (decodedText) => {
                scanner.stop();
                box.style.display = 'none';
                window.location.href = decodedText;
            }
        ).catch(() => {
            box.innerHTML = '<p style="color:#b71c1c;font-size:13px;">เปิดกล้องไม่สำเร็จ กรุณาอนุญาตการใช้กล้อง</p>';
        });
    } else {
        box.style.display = 'none';
        box.innerHTML = '';
    }
});

<?php if ($autoEventId > 0 && $autoCode !== ''): ?>
window.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector(`button[onclick="submitCheckin(<?= $autoEventId ?>, this)"]`);
    if (btn) {
        const input = btn.closest('.checkin-item').querySelector('.checkin-code-input');
        if (input) {
            input.value = <?= json_encode($autoCode) ?>;
            submitCheckin(<?= $autoEventId ?>, btn);
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>