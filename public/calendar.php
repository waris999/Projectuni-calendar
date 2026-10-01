<?php
session_start();
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
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ปฏิทินกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.10/index.global.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.10/index.global.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
    #calendar {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .fc-event { cursor: pointer; }
    .calendar-header-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 12px;
    }
    .modal-overlay2 {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
        z-index: 999;
    }
    .modal-overlay2.active { display: flex; }
    .modal-box2 {
        background: #fff;
        border-radius: 10px;
        padding: 26px;
        width: 360px;
        position: relative;
        max-height: 85vh;
        overflow-y: auto;
    }
    .modal-box2 h2 { color: #1e5631; margin-bottom: 12px; font-size: 17px; }
    .modal-box2 p { font-size: 14px; margin-bottom: 8px; color: #444; }
    .modal-box2 .close-btn {
        position: absolute; top: 12px; right: 16px;
        background: none; border: none; font-size: 18px; cursor: pointer; color: #888;
    }
    .modal-box2 input, .modal-box2 textarea {
        width: 100%; padding: 9px; margin-bottom: 12px;
        border: 1px solid #ccc; border-radius: 6px; font-size: 13px;
        box-sizing: border-box;
    }
    .modal-box2 label { font-size: 13px; color: #444; display:block; margin-bottom:4px; }
    .btn-danger {
        background: #c0392b; color: #fff; border: none; padding: 9px;
        border-radius: 6px; width: 100%; cursor: pointer; font-size: 14px; margin-top: 8px;
    }
    .btn-outline {
        background: #fff; color: #1e5631; border: 1px solid #1e5631;
        padding: 9px; border-radius: 6px; width: 100%; cursor: pointer; font-size: 14px;
    }
    .btn-outline:disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }
    .status-msg { font-size: 12px; margin-top: 8px; text-align:center; }
    .year-check-row {
        display: flex;
        gap: 14px;
        margin-bottom: 14px;
        font-size: 13px;
        flex-wrap: wrap;
    }
    .year-check-row label {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 0;
    }
    .year-check-row input[type="checkbox"] {
        width: auto;
        margin: 0;
    }
</style>
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php">
            <img src="<?= $avatarSrc ?>" alt="avatar">
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
        <a href="logout.php">🚪 ออกจากระบบ</a>
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
        <button class="close-btn" onclick="closeModal('eventModal')">&times;</button>
        <h2 id="evTitle"></h2>
        <p id="evTime"></p>
        <p id="evLocation"></p>
        <p id="evSeats"></p>
        <p id="evHours"></p>
        <p id="evYears"></p>
        <p id="evDescription"></p>

        <?php if (!$isAdmin): ?>
            <button class="btn-outline" id="registerBtn" style="width:100%;"></button>
            <div class="status-msg" id="regStatusMsg"></div>
        <?php else: ?>
            <p id="evCheckinCode" style="font-weight:600;"></p>
            <div id="evQrCode" style="text-align:center; margin: 10px 0;"></div>
            <button class="btn-outline" id="printListBtn" style="width:100%; margin-bottom:8px;">🖨️ พิมพ์รายชื่อผู้เข้าร่วม</button>
            <button class="btn-outline" id="editBtn" style="width:100%; margin-bottom:8px; border-color:#f39c12; color:#f39c12;">✏️ แก้ไขกิจกรรม</button>
            <button class="btn-danger" id="deleteBtn">ลบกิจกรรมนี้</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Modal แก้ไขกิจกรรม -->
<div class="modal-overlay2" id="editModal">
    <div class="modal-box2">
        <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
        <h2>แก้ไขกิจกรรม</h2>

        <input type="hidden" id="editEventId">

        <label>ชื่อกิจกรรม</label>
        <input type="text" id="editTitle">

        <label>รายละเอียด</label>
        <textarea id="editDescription" rows="3"></textarea>

        <label>สถานที่</label>
        <input type="text" id="editLocation">

        <label>จำนวนที่นั่ง (เว้นว่าง = ไม่จำกัด)</label>
        <input type="number" id="editMaxParticipants" min="1" placeholder="เช่น 50">

        <label>ชั่วโมงกิจกรรม</label>
        <input type="number" id="editActivityHours" min="0" max="24" step="0.5" placeholder="เช่น 3">

        <label>รหัสเช็คอิน</label>
        <input type="text" id="editCheckinCode" maxlength="10">

        <label>เปิดให้ชั้นปี (ไม่เลือก = ทุกชั้นปี)</label>
        <div class="year-check-row">
            <label><input type="checkbox" value="1" class="edit-year-checkbox"> ปี 1</label>
            <label><input type="checkbox" value="2" class="edit-year-checkbox"> ปี 2</label>
            <label><input type="checkbox" value="3" class="edit-year-checkbox"> ปี 3</label>
            <label><input type="checkbox" value="4" class="edit-year-checkbox"> ปี 4</label>
        </div>

        <label>วันเวลาเริ่ม</label>
        <input type="datetime-local" id="editStart">

        <label>วันเวลาสิ้นสุด</label>
        <input type="datetime-local" id="editEnd">

        <button class="btn" style="width:100%;" onclick="submitEditEvent()">บันทึกการแก้ไข</button>
        <div class="status-msg" id="editStatusMsg"></div>
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
        events: 'api/events.php',
        eventDidMount: function () {
            // เรียก filter หลัง event ทุกตัว mount เสร็จ (เรียกครั้งเดียวไม่วนซ้ำ เพราะไม่ผูกกับ setProp)
            applyCalendarFilters();
        },
        eventClick: function (info) {
            currentEventId = info.event.id;

            document.getElementById('evTitle').textContent = info.event.title;

            const start = info.event.start ? info.event.start.toLocaleString('th-TH') : '';
            const end = info.event.end ? info.event.end.toLocaleString('th-TH') : '';
            document.getElementById('evTime').textContent = `เวลา: ${start} - ${end}`;

            const loc = info.event.extendedProps.location || '-';
            document.getElementById('evLocation').textContent = `สถานที่: ${loc}`;

            const desc = info.event.extendedProps.description || 'ไม่มีรายละเอียดเพิ่มเติม';
            document.getElementById('evDescription').textContent = desc;

            const maxP = info.event.extendedProps.maxParticipants;
            const regCount = info.event.extendedProps.registeredCount;
            let seatText = 'ที่นั่ง: ไม่จำกัด';
            let isFull = false;
            if (maxP !== null && maxP !== undefined) {
                seatText = `ที่นั่ง: ${regCount} / ${maxP} คน`;
                isFull = regCount >= maxP;
            }
            document.getElementById('evSeats').textContent = seatText;
            const hours = info.event.extendedProps.activityHours;
            document.getElementById('evHours').textContent = hours > 0 ? `ชั่วโมงกิจกรรม: ${hours} ชม.` : '';

            const targetYears = info.event.extendedProps.targetYears;
            let yearText = 'เปิดให้ทุกชั้นปี';
            let isYearAllowed = true;
            if (targetYears) {
                const yearsArr = targetYears.split(',');
                yearText = 'เปิดให้ชั้นปี: ' + yearsArr.map(y => 'ปี ' + y).join(', ');
                isYearAllowed = myYear !== null && yearsArr.includes(String(myYear));
            }
            document.getElementById('evYears').textContent = yearText;

            const now = new Date();
            const eventEnd = info.event.end || info.event.start;
            const isPast = eventEnd && eventEnd < now;

            if (!isAdmin) {
                currentRegistered = !!info.event.extendedProps.registered;
                currentIsFull = isFull;
                currentIsYearAllowed = isYearAllowed;
                currentIsPast = isPast;
                updateRegisterButton();
                document.getElementById('regStatusMsg').textContent = '';
            } else {
                const code = info.event.extendedProps.checkinCode;
                document.getElementById('evCheckinCode').textContent = code ? `รหัสเช็คอิน: ${code}` : '';

                const qrWrap = document.getElementById('evQrCode');
                qrWrap.innerHTML = '';
                if (code) {
                    new QRCode(qrWrap, {
                        text: `${window.location.origin}/uni-calendar/public/checkin.php?auto_event=${currentEventId}&auto_code=${code}`,
                        width: 140,
                        height: 140
                    });
                }
                document.getElementById('printListBtn').onclick = function () {
                    window.open('print_attendees.php?event_id=' + currentEventId, '_blank');
                };
                document.getElementById('editBtn').onclick = function () {
                    openEditModal(currentEventId);
                };
                document.getElementById('deleteBtn').onclick = function () {
                    deleteEvent(currentEventId);
                };
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

    calendar.getEvents().forEach(ev => {
        let visible = true;
        if (searchText && !ev.title.toLowerCase().includes(searchText)) visible = false;
        if (filterValue === 'registered' && !ev.extendedProps.registered) visible = false;
        if (filterValue === 'not_registered' && ev.extendedProps.registered) visible = false;

        const el = ev._def ? null : null; // no-op, keep ev object reference clean
        if (ev.el) {
            ev.el.style.display = visible ? '' : 'none';
        }
    });
}

function updateRegisterButton() {
    const btn = document.getElementById('registerBtn');
    if (currentIsPast && !currentRegistered) {
        btn.textContent = 'กิจกรรมนี้ผ่านไปแล้ว';
        btn.style.borderColor = '#999';
        btn.style.color = '#999';
        btn.disabled = true;
    } else if (currentRegistered) {
        btn.textContent = 'ยกเลิกลงทะเบียน';
        btn.style.borderColor = '#c0392b';
        btn.style.color = '#c0392b';
        btn.disabled = false;
    } else if (!currentIsYearAllowed) {
        btn.textContent = 'ชั้นปีของคุณไม่สามารถลงทะเบียนได้';
        btn.style.borderColor = '#999';
        btn.style.color = '#999';
        btn.disabled = true;
    } else if (currentIsFull) {
        btn.textContent = 'ที่นั่งเต็มแล้ว';
        btn.style.borderColor = '#999';
        btn.style.color = '#999';
        btn.disabled = true;
    } else {
        btn.textContent = 'ลงทะเบียนเข้าร่วม';
        btn.style.borderColor = '#1e5631';
        btn.style.color = '#1e5631';
        btn.disabled = false;
    }
}

async function toggleRegister() {
    const action = currentRegistered ? 'cancel' : 'register';
    const msgEl = document.getElementById('regStatusMsg');
    msgEl.textContent = 'กำลังบันทึก...';

    try {
        const res = await fetch('api/register_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
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
        } else if (result.error === 'year_not_allowed') {
            msgEl.textContent = 'กิจกรรมนี้ไม่เปิดให้ชั้นปีของคุณลงทะเบียน';
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
    document.getElementById('createModal').classList.add('active');
}


async function openEditModal(eventId) {
    closeModal('eventModal');

    // ดึงข้อมูล event เดิมมาเติมฟอร์ม
    const res = await fetch('api/manage_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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
        headers: { 'Content-Type': 'application/json' },
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