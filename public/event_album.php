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

$eventId = (int)($_GET['event_id'] ?? 0);
if ($eventId <= 0) {
    header('Location: history.php');
    exit;
}

$eventModel = new Event($pdo);
$photoModel = new EventPhoto($pdo);
$notifModel = new Notification($pdo);

$event = $eventModel->findById($eventId);
if (!$event) {
    header('Location: history.php');
    exit;
}

// เช็คว่า user ลงทะเบียนกิจกรรมนี้จริงไหม
$checkStmt = $pdo->prepare(
    "SELECT status FROM registrations WHERE user_id = :uid AND event_id = :eid LIMIT 1"
);
$checkStmt->execute(['uid' => $_SESSION['user_id'], 'eid' => $eventId]);
$reg = $checkStmt->fetch();
if (!$reg) {
    header('Location: history.php');
    exit;
}

$unreadCount = $notifModel->countUnread($_SESSION['user_id']);
$isPast      = strtotime($event['end_datetime']) < time();
$error       = '';
$success     = '';

// ===== อัปโหลดรูปใหม่ (เฉพาะกิจกรรมที่ยังไม่ผ่าน) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo']) && !$isPast) {
    $allowedTypes  = ['image/jpeg', 'image/png', 'image/webp'];
    $caption       = trim($_POST['caption'] ?? '');
    $files         = $_FILES['photo'];
    $total         = count($files['name']);
    $uploadedCount = 0;
    $uploadErrors  = [];

    for ($i = 0; $i < $total; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $fileType = mime_content_type($files['tmp_name'][$i]);
        if (!in_array($fileType, $allowedTypes, true)) {
            $uploadErrors[] = $files['name'][$i] . ': ไม่รองรับประเภทไฟล์นี้';
            continue;
        }
        if ($files['size'][$i] > 5 * 1024 * 1024) {
            $uploadErrors[] = $files['name'][$i] . ': ขนาดเกิน 5MB';
            continue;
        }

        $ext         = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $newFilename = 'ep_' . $_SESSION['user_id'] . '_' . $eventId . '_' . time() . '_' . $i . '.' . $ext;
        $destination = __DIR__ . '/img/event_photos/' . $newFilename;

        if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
            $photoModel->addPhoto($_SESSION['user_id'], $eventId, $newFilename, $caption);
            $uploadedCount++;
        }
    }

    if ($uploadedCount > 0) $success = "อัปโหลดสำเร็จ {$uploadedCount} รูป";
    if (!empty($uploadErrors)) $error = implode(', ', $uploadErrors);
}

// ===== ลบรูป (เฉพาะกิจกรรมที่ยังไม่ผ่าน) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo_id']) && !$isPast) {
    $photoId = (int)$_POST['delete_photo_id'];
    $deleted = $photoModel->deletePhoto($photoId, $_SESSION['user_id']);
    if ($deleted) {
        $filePath = __DIR__ . '/img/event_photos/' . $deleted['filename'];
        if (file_exists($filePath)) unlink($filePath);
        $success = 'ลบรูปสำเร็จ';
    }
}

$photos = $photoModel->getPhotos($_SESSION['user_id'], $eventId);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>อัลบั้มกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<style>
    .event-info-banner {
        background: linear-gradient(135deg, #1e5631, #2e7d46);
        color: #fff;
        border-radius: 10px;
        padding: 20px 24px;
        margin-bottom: 20px;
    }
    .event-info-banner h2 { font-size: 20px; margin-bottom: 6px; }
    .event-info-banner p  { font-size: 13px; opacity: 0.85; }

    .status-badge {
        display: inline-block;
        font-size: 12px;
        padding: 3px 10px;
        border-radius: 12px;
        margin-top: 8px;
    }
    .badge-attended  { background: rgba(255,255,255,0.2); color: #fff; }
    .badge-registered { background: rgba(255,200,0,0.3); color: #fff; }
    .badge-past { background: rgba(0,0,0,0.2); color: #fff; }

    /* Upload zone */
    .upload-zone {
        border: 2px dashed #c3e6cb;
        border-radius: 10px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: background 0.2s;
        margin-bottom: 12px;
    }
    .upload-zone:hover { background: #f0faf3; }
    .upload-zone p { font-size: 14px; color: #666; margin-top: 8px; }

    /* Photo grid */
    .photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
        margin-top: 16px;
    }
    .photo-item {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        background: #f4f6f5;
        aspect-ratio: 1;
        cursor: pointer;
    }
    .photo-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.2s;
    }
    .photo-item:hover img { transform: scale(1.05); }
    .photo-item .overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0);
        transition: background 0.2s;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 8px;
    }
    .photo-item:hover .overlay { background: rgba(0,0,0,0.35); }
    .photo-item .caption-text {
        font-size: 12px;
        color: #fff;
        display: none;
        text-shadow: 0 1px 3px rgba(0,0,0,0.8);
    }
    .photo-item:hover .caption-text { display: block; }
    .photo-item .delete-btn {
        position: absolute;
        top: 6px; right: 6px;
        background: rgba(192,57,43,0.85);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 26px; height: 26px;
        font-size: 14px;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .photo-item:hover .delete-btn { display: flex; }

    /* Lightbox */
    .lightbox {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.9);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    .lightbox.active { display: flex; }
    .lightbox img {
        max-width: 90vw;
        max-height: 80vh;
        border-radius: 6px;
        object-fit: contain;
    }
    .lightbox .lb-caption {
        color: #fff;
        font-size: 14px;
        margin-top: 12px;
        text-align: center;
    }
    .lightbox .lb-close {
        position: absolute;
        top: 20px; right: 24px;
        color: #fff; font-size: 28px;
        cursor: pointer; background: none; border: none;
        line-height: 1;
    }
    .lightbox .lb-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        color: #fff;
        font-size: 32px;
        cursor: pointer;
        background: rgba(255,255,255,0.1);
        border: none;
        padding: 8px 14px;
        border-radius: 6px;
    }
    .lightbox .lb-prev { left: 16px; }
    .lightbox .lb-next { right: 16px; }

    .empty-album {
        text-align: center;
        padding: 40px 0;
        color: #999;
    }
    .empty-album .icon { font-size: 48px; margin-bottom: 10px; }

    .past-notice {
        background: #f4f4f4;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 13px;
        color: #888;
        text-align: center;
        margin-bottom: 12px;
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
        <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
        <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <a href="notifications.php">🔔 แจ้งเตือน<?= $unreadCount > 0
            ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?></a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        Home /
        <a href="history.php" style="color:#1e5631;">ประวัติกิจกรรม</a>
        / อัลบั้มรูป
    </div>
    <div class="content">

        <!-- Event Banner -->
        <div class="event-info-banner">
            <h2><?= htmlspecialchars($event['title']) ?></h2>
            <p>
                📍 <?= htmlspecialchars($event['location']) ?>
                &nbsp;|&nbsp;
                🕒 <?= date('d/m/Y H:i', strtotime($event['start_datetime'])) ?>
                – <?= date('H:i', strtotime($event['end_datetime'])) ?>
            </p>
            <?php if ($isPast): ?>
                <span class="status-badge badge-past">🔒 กิจกรรมสิ้นสุดแล้ว</span>
            <?php elseif ($reg['status'] === 'attended'): ?>
                <span class="status-badge badge-attended">✓ เช็คอินแล้ว</span>
            <?php else: ?>
                <span class="status-badge badge-registered">📝 ลงทะเบียนไว้</span>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div style="background:#fdecea; color:#b71c1c; padding:8px 12px; border-radius:6px; margin-bottom:12px; font-size:13px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="background:#e6f4ea; color:#1e5631; padding:8px 12px; border-radius:6px; margin-bottom:12px; font-size:13px;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Card อัปโหลด / แจ้งว่าหมดเวลา -->
        <div class="card">
            <?php if ($isPast): ?>
                <h2>📸 รูปความทรงจำ</h2>
                <div class="past-notice">
                    🔒 กิจกรรมสิ้นสุดแล้ว — ไม่สามารถเพิ่มหรือลบรูปได้ ดูรูปได้อย่างเดียว
                </div>
            <?php else: ?>
                <h2>📸 เพิ่มรูปความทรงจำ</h2>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <label for="photoInput">
                        <div class="upload-zone" id="uploadZone">
                            <div style="font-size:40px;">📷</div>
                            <p>คลิกเพื่อเลือกรูป หรือลากวางรูปที่นี่</p>
                            <p style="font-size:12px; color:#aaa;">รองรับ JPG, PNG, WEBP — ไม่เกิน 5MB ต่อรูป</p>
                            <p id="selectedFiles" style="font-size:13px; color:#1e5631; margin-top:6px;"></p>
                        </div>
                    </label>
                    <input type="file" name="photo[]" id="photoInput"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        onchange="previewFiles(this)">

                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="text" name="caption"
                            placeholder="คำบรรยายรูป (ไม่บังคับ)"
                            style="flex:1; min-width:200px; padding:9px; border:1px solid #ccc; border-radius:6px; font-size:13px;">
                        <button type="submit" class="btn">อัปโหลด</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Card อัลบั้มรูป -->
        <div class="card">
            <h2>🖼️ อัลบั้มกิจกรรม (<?= count($photos) ?> รูป)</h2>

            <?php if (empty($photos)): ?>
                <div class="empty-album">
                    <div class="icon">📭</div>
                    <p>ยังไม่มีรูปในอัลบั้มนี้</p>
                    <?php if (!$isPast): ?>
                        <p style="font-size:13px; margin-top:4px;">
                            เพิ่มรูปความทรงจำของคุณด้านบนได้เลย!
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="photo-grid" id="photoGrid">
                    <?php foreach ($photos as $i => $photo): ?>
                        <div class="photo-item"
                            onclick="openLightbox(<?= $i ?>)"
                            data-src="img/event_photos/<?= htmlspecialchars($photo['filename']) ?>"
                            data-caption="<?= htmlspecialchars($photo['caption'] ?? '') ?>">

                            <img src="img/event_photos/<?= htmlspecialchars($photo['filename']) ?>"
                                alt="<?= htmlspecialchars($photo['caption'] ?? 'รูปกิจกรรม') ?>"
                                loading="lazy">

                            <div class="overlay">
                                <?php if ($photo['caption']): ?>
                                    <div class="caption-text">
                                        <?= htmlspecialchars($photo['caption']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ปุ่มลบ: แสดงเฉพาะกิจกรรมที่ยังไม่ผ่าน -->
                            <?php if (!$isPast): ?>
                                <form method="POST" style="display:contents;"
                                    onsubmit="return confirm('ลบรูปนี้?')">
                                    <input type="hidden" name="delete_photo_id"
                                        value="<?= (int)$photo['id'] ?>">
                                    <button type="submit" class="delete-btn"
                                            onclick="event.stopPropagation()">✕</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
    <button class="lb-close" onclick="closeLightbox()">✕</button>
    <button class="lb-nav lb-prev" onclick="changeLightbox(-1)">&#8249;</button>
    <img src="" id="lbImg" alt="">
    <div class="lb-caption" id="lbCaption"></div>
    <button class="lb-nav lb-next" onclick="changeLightbox(1)">&#8250;</button>
</div>

<script>
// ===== Preview ชื่อไฟล์ที่เลือก =====
function previewFiles(input) {
    const el = document.getElementById('selectedFiles');
    el.textContent = input.files.length > 0
        ? `เลือกแล้ว ${input.files.length} รูป`
        : '';
}

// ===== Drag & Drop =====
const zone      = document.getElementById('uploadZone');
const fileInput = document.getElementById('photoInput');

if (zone && fileInput) {
    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.style.background = '#e6f4ea';
    });
    zone.addEventListener('dragleave', () => {
        zone.style.background = '';
    });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.background = '';
        fileInput.files = e.dataTransfer.files;
        previewFiles(fileInput);
    });
}

// ===== Lightbox =====
const items = document.querySelectorAll('.photo-item[data-src]');
let currentIndex = 0;

function openLightbox(index) {
    if (items.length === 0) return;
    currentIndex = index;
    updateLightbox();
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}

function changeLightbox(dir) {
    currentIndex = (currentIndex + dir + items.length) % items.length;
    updateLightbox();
}

function updateLightbox() {
    const item    = items[currentIndex];
    const src     = item.getAttribute('data-src');
    const caption = item.getAttribute('data-caption');
    document.getElementById('lbImg').src            = src;
    document.getElementById('lbCaption').textContent = caption || '';
}

document.addEventListener('keydown', e => {
    const lb = document.getElementById('lightbox');
    if (!lb.classList.contains('active')) return;
    if (e.key === 'Escape')     closeLightbox();
    if (e.key === 'ArrowLeft')  changeLightbox(-1);
    if (e.key === 'ArrowRight') changeLightbox(1);
});

document.getElementById('lightbox').addEventListener('click', function (e) {
    if (e.target === this) closeLightbox();
});
</script>

</body>
</html>