<?php
require_once __DIR__ . '/../src/security.php';
/* session initialized by security.php */
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Notification.php';

$isAdmin = $_SESSION['role'] === 'admin';
$notifModel = new Notification($pdo);
$unreadCount = $isAdmin ? 0 : $notifModel->countUnread($_SESSION['user_id']);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.svg';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ปฏิทินกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.10/index.global.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
    /* ── Calendar wrapper ─────────────────────────────── */
    #calendar {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .fc-event { cursor: pointer; border-radius: 6px !important; font-size: 12px !important; }
    .fc-daygrid-event { padding: 2px 6px !important; }
    .fc-toolbar-title { font-size: 18px !important; font-weight: 600 !important; }
    .fc-button-primary {
        background: #1a4a2e !important;
        border-color: #1a4a2e !important;
        border-radius: 6px !important;
        font-size: 13px !important;
    }
    .fc-button-primary:hover { background: #2d7a4f !important; border-color: #2d7a4f !important; }
    .fc-today-button { opacity: 0.85 !important; }
    .fc-day-today { background: rgba(26,74,46,0.04) !important; }

    /* ── Filters bar ──────────────────────────────────── */
    .calendar-filters {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    .calendar-filters input {
        flex: 1;
        min-width: 200px;
        padding: 9px 14px 9px 38px;
        border: 1.5px solid #d4e3d9;
        border-radius: 8px;
        font-size: 14px;
        background: #f7f9f5 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7f72' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center;
        outline: none;
        transition: border-color .15s;
    }
    .calendar-filters input:focus { border-color: #2d7a4f; background-color: #fff; }
    .calendar-filters select {
        padding: 9px 32px 9px 12px;
        border: 1.5px solid #d4e3d9;
        border-radius: 8px;
        font-size: 14px;
        background: #f7f9f5;
        outline: none;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7f72' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        transition: border-color .15s;
    }
    .calendar-filters select:focus { border-color: #2d7a4f; }

    /* ── Modal overlay ────────────────────────────────── */
    .modal-overlay2 {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,25,18,0.55);
        backdrop-filter: blur(3px);
        align-items: center;
        justify-content: center;
        z-index: 999;
        padding: 16px;
    }
    .modal-overlay2.active { display: flex; }

    /* ── Modal box ────────────────────────────────────── */
    .modal-box2 {
        background: #fff;
        border-radius: 16px;
        width: 420px;
        max-width: 100%;
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.18);
        animation: modalIn .18s ease;
    }
    @keyframes modalIn {
        from { opacity:0; transform: translateY(10px) scale(.98); }
        to   { opacity:1; transform: translateY(0) scale(1); }
    }
    .modal-header {
        padding: 22px 24px 16px;
        border-bottom: 1px solid #eef2ef;
        position: sticky; top: 0;
        background: #fff;
        border-radius: 16px 16px 0 0;
        z-index: 1;
    }
    .modal-header h2 {
        color: #1a4a2e;
        font-size: 18px;
        font-weight: 700;
        margin: 0;
        padding-right: 28px;
        line-height: 1.3;
    }
    .modal-body { padding: 18px 24px 24px; }
    .modal-box2 .close-btn {
        position: absolute; top: 16px; right: 18px;
        background: #f0f4f0; border: none;
        width: 28px; height: 28px; border-radius: 50%;
        font-size: 16px; cursor: pointer; color: #6b7f72;
        display: flex; align-items: center; justify-content: center;
        transition: background .15s;
    }
    .modal-box2 .close-btn:hover { background: #e0ebe2; color: #1a4a2e; }

    /* Event detail rows */
    .ev-meta {
        display: flex; flex-direction: column; gap: 8px;
        margin-bottom: 18px;
    }
    .ev-meta-row {
        display: flex; align-items: flex-start; gap: 10px;
        font-size: 14px; color: #4a5e50;
    }
    .ev-meta-row .icon {
        width: 20px; text-align: center;
        flex-shrink: 0; margin-top: 1px;
    }
    .ev-meta-row strong { color: #1a4a2e; }
    .ev-desc {
        font-size: 14px; color: #6b7f72;
        background: #f7f9f5; border-radius: 8px;
        padding: 12px 14px; margin-bottom: 18px;
        line-height: 1.6;
    }
    .ev-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 10px; border-radius: 20px;
        font-size: 12px; font-weight: 600;
    }
    .ev-badge-green  { background: #e6f4ea; color: #1e5631; }
    .ev-badge-orange { background: #fff8e1; color: #b7791f; }
    .ev-badge-red    { background: #fdecea; color: #b71c1c; }
    .ev-badge-grey   { background: #f4f4f4; color: #888; }

    /* QR section */
    .qr-wrap {
        background: #f7f9f5; border-radius: 10px;
        padding: 16px; text-align: center;
        margin-bottom: 16px;
    }
    .qr-wrap .qr-label {
        font-size: 11px; color: #6b7f72;
        margin-top: 8px; letter-spacing: .04em;
    }
    .checkin-code-badge {
        display: inline-block;
        background: #1a4a2e; color: #fff;
        font-size: 20px; font-weight: 700;
        letter-spacing: .12em;
        padding: 8px 20px; border-radius: 8px;
        margin-bottom: 12px;
    }

    /* Form fields */
    .field-group { margin-bottom: 14px; }
    .field-group label {
        display: block; font-size: 12px;
        font-weight: 600; color: #4a5e50;
        margin-bottom: 5px; letter-spacing: .02em;
    }
    .modal-box2 input:not([type=checkbox]),
    .modal-box2 textarea,
    .modal-box2 select {
        width: 100%; padding: 9px 12px;
        border: 1.5px solid #d4e3d9; border-radius: 8px;
        font-size: 14px; font-family: inherit;
        background: #f7f9f5; color: #1c2b22;
        outline: none; transition: border-color .15s, box-shadow .15s;
        box-sizing: border-box;
    }
    .modal-box2 input:focus:not([type=checkbox]),
    .modal-box2 textarea:focus {
        border-color: #2d7a4f;
        box-shadow: 0 0 0 3px rgba(45,122,79,.1);
        background: #fff;
    }
    .modal-box2 textarea { resize: vertical; min-height: 80px; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    /* Year checkboxes */
    .year-check-row {
        display: flex; gap: 8px; flex-wrap: wrap;
        margin-top: 2px;
    }
    .year-chip {
        display: flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px;
        border: 1.5px solid #d4e3d9;
        font-size: 13px; cursor: pointer;
        transition: background .15s, border-color .15s;
        user-select: none;
    }
    .year-chip:has(input:checked) {
        background: #e6f4ea; border-color: #2d7a4f; color: #1a4a2e; font-weight: 600;
    }
    .year-chip input { display: none; }

    /* Action buttons */
    .modal-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
    .btn-primary-modal {
        background: #1a4a2e; color: #fff; border: none;
        padding: 11px; border-radius: 8px; width: 100%;
        cursor: pointer; font-size: 14px; font-weight: 600;
        font-family: inherit; transition: background .15s;
    }
    .btn-primary-modal:hover { background: #2d7a4f; }
    .btn-primary-modal:disabled { opacity: .6; cursor: not-allowed; }
    .btn-outline {
        background: #fff; color: #1a4a2e;
        border: 1.5px solid #1a4a2e;
        padding: 10px; border-radius: 8px; width: 100%;
        cursor: pointer; font-size: 14px;
        font-family: inherit; transition: background .15s;
    }
    .btn-outline:hover { background: #f0f7f2; }
    .btn-outline:disabled { opacity: .6; cursor: not-allowed; }
    .btn-warning {
        background: #fff; color: #b7791f;
        border: 1.5px solid #f6ad55;
        padding: 10px; border-radius: 8px; width: 100%;
        cursor: pointer; font-size: 14px;
        font-family: inherit; transition: background .15s;
    }
    .btn-warning:hover { background: #fffaf0; }
    .btn-danger {
        background: #fff; color: #b71c1c;
        border: 1.5px solid #f08080;
        padding: 10px; border-radius: 8px; width: 100%;
        cursor: pointer; font-size: 14px;
        font-family: inherit; transition: background .15s;
    }
    .btn-danger:hover { background: #fff5f5; }
    .status-msg {
        font-size: 12px; margin-top: 6px;
        text-align: center; color: #6b7f72;
    }
    .calendar-header-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 12px;
    }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php">
            <img src="<?= $avatarSrc ?>" alt="รูปโปรไฟล์" onerror="this.onerror=null;this.src='img/avatar-placeholder.svg';">
        </a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">
            <?php if ($isAdmin): ?>
                เจ้าหน้าที่
            <?php else: ?>
                นักศึกษา<?= !empty($_SESSION['student_year']) ? ' ชั้นปีที่ ' . (int) $_SESSION['student_year'] : '' ?>
            <?php endif; ?>
        </div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php" class="active">📅 ปฏิทินกิจกรรม</a>
        <?php if ($isAdmin): ?>
        <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
        <a href="admin_hours.php">⏱️ ชั่วโมงกิจกรรม</a>
        <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <?php else: ?>
        <a href="checkin.php">✅ เช็คอินกิจกรรม</a>
        <a href="history.php">🕘 ประวัติกิจกรรม</a>
        <?php endif; ?>
        <a href="notifications.php">🔔 แจ้งเตือน<?= $unreadCount > 0 ? ' <span class="nav-badge">' . $unreadCount . '</span>' : '' ?></a>
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / ปฏิทินกิจกรรม</div>
    <div class="content">

        <?php if ($isAdmin): ?>
        <div class="calendar-header-actions">
            <button class="btn" onclick="openCreateModal()">+ เพิ่มกิจกรรม</button>
        </div>
        <?php endif; ?>

        <div class="calendar-filters">
            <input type="text" id="searchEventInput" placeholder="🔍 ค้นหาชื่อกิจกรรม...">
            <?php if (!$isAdmin): ?>
            <select id="filterRegistered">
                <option value="all">ทั้งหมด</option>
                <option value="registered">ที่ลงทะเบียนแล้ว</option>
                <option value="not_registered">ยังไม่ลงทะเบียน</option>
            </select>
            <?php endif; ?>
        </div>

        <div id="calendar"></div>
    </div>
</div>

<!-- Modal รายละเอียดกิจกรรม -->
<div class="modal-overlay2" id="eventModal">
    <div class="modal-box2">
        <div class="modal-header">
            <button class="close-btn" onclick="closeModal('eventModal')">&times;</button>
            <h2 id="evTitle"></h2>
            <div id="evBadges" style="margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;"></div>
        </div>
        <div class="modal-body">
            <div class="ev-meta" id="evMeta"></div>
            <div class="ev-desc" id="evDescription" style="display:none;"></div>

            <?php if (!$isAdmin): ?>
                <div class="modal-actions">
                    <button class="btn-primary-modal" id="registerBtn"></button>
                    <div class="status-msg" id="regStatusMsg"></div>
                </div>
            <?php else: ?>
                <div id="evQrSection" style="display:none;">
                    <div class="qr-wrap">
                        <div id="evCheckinCodeBadge"></div>
                        <div id="evQrCode"></div>
                        <div class="qr-label">QR สำหรับเช็คอิน</div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn-outline" id="printListBtn">🖨️ พิมพ์รายชื่อผู้เข้าร่วม</button>
                    <button class="btn-warning" id="editBtn">✏️ แก้ไขกิจกรรม</button>
                    <button class="btn-danger" id="deleteBtn">ลบกิจกรรมนี้</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal แก้ไขกิจกรรม -->
<div class="modal-overlay2" id="editModal">
    <div class="modal-box2">
        <div class="modal-header">
            <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            <h2>แก้ไขกิจกรรม</h2>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editEventId">
            <div class="field-group">
                <label>ชื่อกิจกรรม</label>
                <input type="text" id="editTitle" placeholder="ชื่อกิจกรรม">
            </div>
            <div class="field-group">
                <label>รายละเอียด</label>
                <textarea id="editDescription" rows="3" placeholder="รายละเอียด"></textarea>
            </div>
            <div class="field-group">
                <label>สถานที่</label>
                <input type="text" id="editLocation" placeholder="สถานที่จัดกิจกรรม">
            </div>
            <div class="field-row">
                <div class="field-group">
                    <label>จำนวนที่นั่ง</label>
                    <input type="number" id="editMaxParticipants" min="1" placeholder="ไม่จำกัด">
                </div>
                <div class="field-group">
                    <label>ชั่วโมงกิจกรรม</label>
                    <input type="number" id="editActivityHours" min="0" max="24" step="0.5" placeholder="เช่น 3">
                </div>
            </div>
            <div class="field-group">
                <label>รหัสเช็คอิน</label>
                <input type="text" id="editCheckinCode" maxlength="10" placeholder="เช่น ABC123">
            </div>
            <div class="field-group">
                <label>เปิดให้ชั้นปี <span style="font-weight:400;color:#6b7f72">(ไม่เลือก = ทุกชั้นปี)</span></label>
                <div class="year-check-row">
                    <?php for($y=1;$y<=8;$y++): ?>
                    <label class="year-chip"><input type="checkbox" value="<?=$y?>" class="edit-year-checkbox"> ปี <?=$y?></label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="field-row">
                <div class="field-group">
                    <label>วันเวลาเริ่ม</label>
                    <input type="datetime-local" id="editStart">
                </div>
                <div class="field-group">
                    <label>วันเวลาสิ้นสุด</label>
                    <input type="datetime-local" id="editEnd">
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary-modal" onclick="submitEditEvent()">บันทึกการแก้ไข</button>
                <div class="status-msg" id="editStatusMsg"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- Modal สร้างกิจกรรมใหม่ -->
<div class="modal-overlay2" id="createModal">
    <div class="modal-box2">
        <div class="modal-header">
            <button class="close-btn" onclick="closeModal('createModal')">&times;</button>
            <h2>เพิ่มกิจกรรมใหม่</h2>
        </div>
        <div class="modal-body">
            <div class="field-group">
                <label>ชื่อกิจกรรม <span style="color:#c0392b">*</span></label>
                <input type="text" id="createTitle" placeholder="ชื่อกิจกรรม">
            </div>
            <div class="field-group">
                <label>รายละเอียด</label>
                <textarea id="createDescription" rows="3" placeholder="รายละเอียดกิจกรรม"></textarea>
            </div>
            <div class="field-group">
                <label>สถานที่</label>
                <input type="text" id="createLocation" placeholder="สถานที่จัดกิจกรรม">
            </div>
            <div class="field-row">
                <div class="field-group">
                    <label>จำนวนที่นั่ง</label>
                    <input type="number" id="createMaxParticipants" min="1" placeholder="ไม่จำกัด">
                </div>
                <div class="field-group">
                    <label>ชั่วโมงกิจกรรม</label>
                    <input type="number" id="createActivityHours" min="0" max="24" step="0.5" placeholder="เช่น 3">
                </div>
            </div>
            <div class="field-group">
                <label>รหัสเช็คอิน <span style="font-weight:400;color:#6b7f72">(เว้น = สุ่มอัตโนมัติ)</span></label>
                <input type="text" id="createCheckinCode" maxlength="10" placeholder="เช่น ABC123">
            </div>
            <div class="field-group">
                <label>เปิดให้ชั้นปี <span style="font-weight:400;color:#6b7f72">(ไม่เลือก = ทุกชั้นปี)</span></label>
                <div class="year-check-row">
                    <?php for($y=1;$y<=8;$y++): ?>
                    <label class="year-chip"><input type="checkbox" value="<?=$y?>" class="create-year-checkbox"> ปี <?=$y?></label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="field-row">
                <div class="field-group">
                    <label>วันเวลาเริ่ม <span style="color:#c0392b">*</span></label>
                    <input type="datetime-local" id="createStart">
                </div>
                <div class="field-group">
                    <label>วันเวลาสิ้นสุด <span style="color:#c0392b">*</span></label>
                    <input type="datetime-local" id="createEnd">
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary-modal" onclick="submitCreateEvent()">สร้างกิจกรรม</button>
                <div class="status-msg" id="createStatusMsg"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
const myYear = <?= json_encode($_SESSION['student_year'] ?? null) ?>;

let calendar;
let currentEventId = null;
let currentRegistered = false;
let currentIsFull = false;
let currentIsYearAllowed = true;
let currentIsPast = false;
let calendarSearchText = '';
let calendarFilterValue = 'all';

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'th',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            const params = new URLSearchParams();
            if (calendarSearchText) params.set('search', calendarSearchText);
            if (calendarFilterValue && calendarFilterValue !== 'all') params.set('filter', calendarFilterValue);
            fetch('api/events.php?' + params.toString())
                .then(r => r.json())
                .then(data => successCallback(data))
                .catch(e => failureCallback(e));
        },
        eventClick: function (info) {
            currentEventId = info.event.id;

            // Header
            document.getElementById('evTitle').textContent = info.event.title;

            // Meta rows
            const start    = info.event.start;
            const end      = info.event.end || start;
            const loc      = info.event.extendedProps.location || '-';
            const hours    = info.event.extendedProps.activityHours;
            const maxP     = info.event.extendedProps.maxParticipants;
            const regCount = info.event.extendedProps.registeredCount;
            const targetYears = info.event.extendedProps.targetYears;
            const desc     = info.event.extendedProps.description || '';

            const fmt = d => d ? d.toLocaleString('th-TH', {dateStyle:'medium', timeStyle:'short'}) : '';
            let isFull = false;
            let seatText = 'ไม่จำกัดที่นั่ง';
            if (maxP !== null && maxP !== undefined) {
                seatText = `${regCount} / ${maxP} คน`;
                isFull = regCount >= maxP;
            }

            let isYearAllowed = true;
            let yearText = 'ทุกชั้นปี';
            if (targetYears) {
                const yearsArr = targetYears.split(',');
                yearText = yearsArr.map(y => 'ปี ' + y).join(', ');
                isYearAllowed = myYear !== null && yearsArr.includes(String(myYear));
            }

            const now = new Date();
            const isPast = start && start <= now;

            // Build meta
            const metaEl = document.getElementById('evMeta');
            const escapeHtml = value => { const el = document.createElement('span'); el.textContent = String(value); return el.innerHTML; };
            metaEl.innerHTML = [
                `<div class="ev-meta-row"><span class="icon">🕒</span><span>${fmt(start)} — ${fmt(end)}</span></div>`,
                `<div class="ev-meta-row"><span class="icon">📍</span><span>${escapeHtml(loc)}</span></div>`,
                hours > 0 ? `<div class="ev-meta-row"><span class="icon">⏱️</span><span><strong>${hours} ชั่วโมง</strong></span></div>` : '',
                `<div class="ev-meta-row"><span class="icon">👥</span><span>${seatText}</span></div>`,
                `<div class="ev-meta-row"><span class="icon">🎓</span><span>${escapeHtml(yearText)}</span></div>`,
            ].join('');

            // Description
            const descEl = document.getElementById('evDescription');
            if (desc) { descEl.textContent = desc; descEl.style.display = ''; }
            else { descEl.style.display = 'none'; }

            // Badges
            const badgesEl = document.getElementById('evBadges');
            let badges = [];
            if (isPast) badges.push('<span class="ev-badge ev-badge-grey">สิ้นสุดแล้ว</span>');
            else        badges.push('<span class="ev-badge ev-badge-green">กำลังรับสมัคร</span>');
            if (isFull) badges.push('<span class="ev-badge ev-badge-red">ที่นั่งเต็ม</span>');
            badgesEl.innerHTML = badges.join('');

            if (!isAdmin) {
                currentRegistered = !!info.event.extendedProps.registered;
                currentAttended = !!info.event.extendedProps.attended;
                currentIsFull = isFull;
                currentIsYearAllowed = isYearAllowed;
                currentIsPast = isPast;
                updateRegisterButton();
                document.getElementById('regStatusMsg').textContent = '';
            } else {
                const code = info.event.extendedProps.checkinCode;
                const qrSection = document.getElementById('evQrSection');
                const codeBadge = document.getElementById('evCheckinCodeBadge');
                const qrWrap    = document.getElementById('evQrCode');
                qrWrap.innerHTML = '';
                codeBadge.innerHTML = '';
                if (code) {
                    qrSection.style.display = '';
                    codeBadge.innerHTML = `<div class="checkin-code-badge">${escapeHtml(code)}</div>`;
                    new QRCode(qrWrap, {
                        text: new URL(`checkin.php?auto_event=${encodeURIComponent(currentEventId)}&auto_code=${encodeURIComponent(code)}`, window.location.href).href,
                        width: 140, height: 140
                    });
                } else {
                    qrSection.style.display = 'none';
                }
                document.getElementById('printListBtn').onclick = () =>
                    window.open('print_attendees.php?event_id=' + currentEventId, '_blank');
                document.getElementById('editBtn').onclick = () => openEditModal(currentEventId);
                document.getElementById('deleteBtn').onclick = () => deleteEvent(currentEventId);
            }

            document.getElementById('eventModal').classList.add('active');
        }
    });
    calendar.render();

    if (!isAdmin) {
        document.getElementById('registerBtn').onclick = toggleRegister;
    }

    document.getElementById('searchEventInput').addEventListener('input', applyCalendarFilters);
    const filterSelect = document.getElementById('filterRegistered');
    if (filterSelect) filterSelect.addEventListener('change', applyCalendarFilters);
});

function applyCalendarFilters() {
    if (!calendar) return;
    const searchText = document.getElementById('searchEventInput').value.trim().toLowerCase();
    const filterSelect = document.getElementById('filterRegistered');
    const filterValue = filterSelect ? filterSelect.value : 'all';

    // FullCalendar 6: ใช้ setProp ไม่ได้ ต้องใช้ refetchEvents + eventDataTransform หรือ filter ผ่าน hidden
    // วิธีที่ถูกต้องคือ re-filter source แล้ว refetch
    calendarSearchText = searchText;
    calendarFilterValue = filterValue;
    calendar.refetchEvents();
}

let currentAttended = false;
function updateRegisterButton() {
    const btn = document.getElementById('registerBtn');
    // reset style
    btn.className = 'btn-primary-modal';
    btn.disabled = false;
    if (currentAttended) { btn.textContent = 'เช็คอินแล้ว'; btn.disabled = true; return; }
    if (currentIsPast) {
        btn.textContent = 'กิจกรรมนี้ผ่านไปแล้ว';
        btn.style.background = '#e0e0e0'; btn.style.color = '#999'; btn.style.cursor = 'not-allowed';
        btn.disabled = true;
    } else if (currentRegistered) {
        btn.textContent = 'ยกเลิกลงทะเบียน';
        btn.style.background = '#fff'; btn.style.color = '#b71c1c';
        btn.style.border = '1.5px solid #f08080'; btn.style.cursor = 'pointer';
    } else if (!currentIsYearAllowed) {
        btn.textContent = 'ชั้นปีของคุณไม่สามารถลงทะเบียนได้';
        btn.style.background = '#e0e0e0'; btn.style.color = '#999'; btn.style.cursor = 'not-allowed';
        btn.disabled = true;
    } else if (currentIsFull) {
        btn.textContent = 'ที่นั่งเต็มแล้ว';
        btn.style.background = '#e0e0e0'; btn.style.color = '#999'; btn.style.cursor = 'not-allowed';
        btn.disabled = true;
    } else {
        btn.textContent = 'ลงทะเบียนเข้าร่วม';
        btn.style.background = ''; btn.style.color = ''; btn.style.border = ''; btn.style.cursor = '';
    }
}

async function toggleRegister() {
    const action = currentRegistered ? 'cancel' : 'register';
    const msgEl = document.getElementById('regStatusMsg');
    msgEl.textContent = 'กำลังบันทึก...';

    try {
        const res = await fetch('api/register_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token']) ?> },
            body: JSON.stringify({ event_id: currentEventId, action: action })
        });
        const result = await res.json();

        if (result.success) {
            currentRegistered = !currentRegistered;
            updateRegisterButton();
            msgEl.textContent = currentRegistered ? 'ลงทะเบียนสำเร็จ' : 'ยกเลิกลงทะเบียนแล้ว';
            calendar.refetchEvents();
        } else if (result.error === 'full') {
            msgEl.textContent = 'ขออภัย ที่นั่งเต็มแล้ว';
            calendar.refetchEvents();
        } else if (result.error === 'already_checked_in') {
            msgEl.textContent = 'เช็คอินแล้ว ไม่สามารถเปลี่ยนการลงทะเบียนได้';
            calendar.refetchEvents();
        } else if (result.error === 'year_not_allowed') {
            msgEl.textContent = 'กิจกรรมนี้ไม่เปิดให้ชั้นปีของคุณลงทะเบียน';
        } else if (result.error === 'event_started') {
            msgEl.textContent = 'ปิดรับลงทะเบียนแล้ว เนื่องจากกิจกรรมเริ่มต้นแล้ว';
            currentIsFull = true;
            updateRegisterButton();
        } else {
            msgEl.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    } catch (err) {
        msgEl.textContent = 'เชื่อมต่อไม่สำเร็จ';
    }
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

<?php if ($isAdmin): ?>
function openCreateModal() {
    // reset ฟอร์ม
    ['createTitle','createDescription','createLocation','createMaxParticipants',
     'createActivityHours','createCheckinCode','createStart','createEnd'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.querySelectorAll('.create-year-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('createStatusMsg').textContent = '';
    document.getElementById('createModal').classList.add('active');
}

async function submitCreateEvent() {
    const title       = document.getElementById('createTitle').value.trim();
    const description = document.getElementById('createDescription').value.trim();
    const location    = document.getElementById('createLocation').value.trim();
    const maxP        = document.getElementById('createMaxParticipants').value.trim();
    const hours       = document.getElementById('createActivityHours').value.trim();
    const checkinCode = document.getElementById('createCheckinCode').value.trim();
    const targetYears = Array.from(document.querySelectorAll('.create-year-checkbox:checked')).map(cb => cb.value);
    const start       = document.getElementById('createStart').value;
    const end         = document.getElementById('createEnd').value;
    const msgEl       = document.getElementById('createStatusMsg');

    if (!title || !start || !end) {
        msgEl.textContent = 'กรุณากรอกชื่อกิจกรรมและวันเวลาให้ครบ';
        msgEl.style.color = '#b71c1c';
        return;
    }

    msgEl.textContent = 'กำลังบันทึก...';
    msgEl.style.color = '#666';

    const res = await fetch('api/manage_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token']) ?> },
        body: JSON.stringify({
            action:           'create',
            title:            title,
            description:      description,
            location:         location,
            max_participants: maxP,
            activity_hours:  hours,
            checkin_code:    checkinCode,
            target_years:    targetYears,
            start_datetime:  start.replace('T', ' ') + ':00',
            end_datetime:    end.replace('T', ' ') + ':00',
        })
    });
    const result = await res.json();

    if (result.success) {
        msgEl.textContent = 'สร้างกิจกรรมสำเร็จ!';
        msgEl.style.color = '#1e5631';
        calendar.refetchEvents();
        setTimeout(() => closeModal('createModal'), 900);
    } else {
        msgEl.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        msgEl.style.color = '#b71c1c';
    }
}


async function openEditModal(eventId) {
    closeModal('eventModal');

    // ดึงข้อมูล event เดิมมาเติมฟอร์ม
    const res = await fetch('api/manage_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token']) ?> },
        body: JSON.stringify({ action: 'get', event_id: eventId })
    });
    const result = await res.json();
    if (!result.success) { alert('โหลดข้อมูลไม่สำเร็จ'); return; }

    const ev = result.event;
    document.getElementById('editEventId').value     = ev.id;
    document.getElementById('editTitle').value       = ev.title;
    document.getElementById('editDescription').value = ev.description || '';
    document.getElementById('editLocation').value    = ev.location || '';
    document.getElementById('editMaxParticipants').value = ev.max_participants || '';
    document.getElementById('editActivityHours').value   = ev.activity_hours || '';
    document.getElementById('editCheckinCode').value     = ev.checkin_code || '';

    // datetime-local ต้องการรูปแบบ "YYYY-MM-DDTHH:MM"
    document.getElementById('editStart').value = ev.start_datetime.replace(' ', 'T').slice(0, 16);
    document.getElementById('editEnd').value   = ev.end_datetime.replace(' ', 'T').slice(0, 16);

    // เช็ค checkbox ชั้นปี
    const years = ev.target_years ? ev.target_years.split(',') : [];
    document.querySelectorAll('.edit-year-checkbox').forEach(cb => {
        cb.checked = years.includes(cb.value);
    });

    document.getElementById('editStatusMsg').textContent = '';
    document.getElementById('editModal').classList.add('active');
}

async function submitEditEvent() {
    const eventId      = document.getElementById('editEventId').value;
    const title        = document.getElementById('editTitle').value.trim();
    const description  = document.getElementById('editDescription').value.trim();
    const location     = document.getElementById('editLocation').value.trim();
    const maxP         = document.getElementById('editMaxParticipants').value.trim();
    const hours        = document.getElementById('editActivityHours').value.trim();
    const checkinCode  = document.getElementById('editCheckinCode').value.trim();
    const targetYears  = Array.from(document.querySelectorAll('.edit-year-checkbox:checked')).map(cb => cb.value);
    const start        = document.getElementById('editStart').value;
    const end          = document.getElementById('editEnd').value;
    const msgEl        = document.getElementById('editStatusMsg');

    if (!title || !start || !end) {
        msgEl.textContent = 'กรุณากรอกชื่อกิจกรรมและวันเวลาให้ครบ';
        return;
    }

    msgEl.textContent = 'กำลังบันทึก...';

    const res = await fetch('api/manage_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token']) ?> },
        body: JSON.stringify({
            action:          'edit',
            event_id:        eventId,
            title:           title,
            description:     description,
            location:        location,
            max_participants: maxP,
            activity_hours:  hours,
            checkin_code:    checkinCode,
            target_years:    targetYears,
            start_datetime:  start.replace('T', ' ') + ':00',
            end_datetime:    end.replace('T', ' ') + ':00',
        })
    });
    const result = await res.json();

    if (result.success) {
        msgEl.textContent = 'บันทึกสำเร็จ!';
        calendar.refetchEvents();
        setTimeout(() => closeModal('editModal'), 800);
    } else {
        msgEl.textContent = 'บันทึกไม่สำเร็จ กรุณาลองใหม่';
    }
}

<?php endif; ?>
</script>


</body>
</html>
