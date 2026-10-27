<?php
require_once __DIR__.'/../src/security.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php');exit; }
if (($_SESSION['role'] ?? '')!=='student') { header('Location: dashboard.php');exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../src/Event.php';
require_once __DIR__.'/../src/Notification.php';
require_once __DIR__.'/../src/AttendanceReview.php';
require_once __DIR__.'/../src/Avatar.php';
$reviewModel=new AttendanceReview($pdo);$error='';
$messages=['invalid_image'=>'กรุณาเลือกรูปหลักฐาน JPG, PNG หรือ WEBP ที่ถูกต้อง','invalid_caption'=>'คำอธิบายต้องไม่เกิน 500 ตัวอักษร','forbidden'=>'ไม่สามารถส่งหลักฐานของรายการนี้ได้','not_found'=>'กรุณายืนยันรหัสกิจกรรมก่อนส่งหลักฐาน','deadline_passed'=>'หมดเวลาส่งหลักฐานแล้ว (ไม่เกิน 2 วันหลังสิ้นสุดกิจกรรม)','already_pending'=>'ส่งหลักฐานแล้ว กำลังรอแอดมินอนุมัติ','already_approved'=>'กิจกรรมนี้ได้รับอนุมัติแล้ว'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $regId=filter_var($_POST['registration_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $caption=is_string($_POST['caption'] ?? null) ? trim($_POST['caption']) : '';
    try {
        $result=$regId ? $reviewModel->submit((int)$_SESSION['user_id'],$regId,$_POST['evidence_data'] ?? '',$caption) : 'not_found';
        if ($result==='pending') { header('Location: checkin.php?sent=1');exit; }
        $error=$messages[$result] ?? 'ส่งหลักฐานไม่สำเร็จ กรุณาลองใหม่';
    } catch (Throwable $e) { error_log('Evidence submission failed: '.get_class($e));$error='ส่งหลักฐานไม่สำเร็จ กรุณาลองใหม่'; }
}
$myEvents=(new Event($pdo))->getRegisteredByUserForCheckin((int)$_SESSION['user_id']);
$unreadCount=(new Notification($pdo))->countUnread((int)$_SESSION['user_id']);
$avatarSrc=currentAvatarUrl($pdo,(int)$_SESSION['user_id']);
$now=$pdo->query('SELECT NOW()')->fetchColumn();
$autoEventId=(int)($_GET['auto_event'] ?? 0);$autoCode=is_string($_GET['auto_code'] ?? null) ? $_GET['auto_code'] : '';
$h=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
?>
<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>เช็กอินและส่งหลักฐาน | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__.'/css/style.css') ?>">
<link rel="stylesheet" href="css/attendance.css?v=<?= filemtime(__DIR__.'/css/attendance.css') ?>">
<style>
.checkin-form{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.checkin-form input{padding:9px;width:130px;border:1px solid #cad8ce;border-radius:6px;font:inherit;font-size:14px;min-height:44px}.checkin-form button{background:#1e5631;color:#fff;border:0;border-radius:6px;padding:9px 12px;font:inherit;font-size:14px;min-height:44px;cursor:pointer}.checkin-form .checkin-scan-button{background:#edf6ef;color:#1e5631;border:1px solid #b9d5c2}.checkin-msg{font-size:13px;margin-top:8px}.checkin-scanner-box{width:100%;max-width:340px;margin-top:12px}.checkin-form button:disabled{opacity:.6}@media(max-width:600px){.checkin-form input{flex:1;min-width:0;width:90px}}
</style></head><body class="app-layout">
<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="รูปโปรไฟล์" width="70" height="70" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';"></a>
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
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
    </nav>
</aside>
<main class="main"><div class="topbar">Home / เช็กอินและส่งหลักฐาน</div><div class="content">
<?php if ($error): ?><p class="attendance-error" role="alert"><?= $h($error) ?></p><?php endif; ?>
<?php if (($_GET['sent'] ?? '')==='1'): ?><p class="attendance-success" role="status">ส่งหลักฐานแล้ว รอแอดมินตรวจสอบ ชั่วโมงจะเพิ่มเมื่อได้รับอนุมัติ</p><?php endif; ?>
<section class="card"><h2>ยืนยันรหัสและส่งหลักฐานการเข้าร่วม</h2>
<p class="attendance-intro">1. กรอกรหัสหรือสแกน QR ของกิจกรรม → 2. ส่งรูปหลักฐาน → 3. รอแอดมินอนุมัติ<br>การยืนยันรหัสเพียงอย่างเดียวยังไม่ได้รับชั่วโมง ส่งหลักฐานได้ภายใน 2 วันหลังสิ้นสุดกิจกรรม</p>
<?php if (!$myEvents): ?><p>คุณยังไม่ได้ลงทะเบียนกิจกรรม</p><?php endif; ?>
<?php foreach ($myEvents as $ev):
    $state=$ev['review_status'] ?? '';$approved=$ev['status']==='attended';
    $deadline=(new DateTimeImmutable($ev['end_datetime']))->modify('+2 days')->format('Y-m-d H:i:s');
?>
<article class="attendance-row checkin-item"><div class="attendance-row-head">
<div><h3><?= $h($ev['title']) ?></h3><p class="attendance-meta">📍 <?= $h($ev['location']) ?><br><?= $h(date('d/m/Y H:i',strtotime($ev['start_datetime']))) ?> – <?= $h(date('d/m/Y H:i',strtotime($ev['end_datetime']))) ?></p></div>
<div>
<?php if ($approved): ?><span class="attendance-state approved">ได้รับชั่วโมงแล้ว ✓</span>
<?php elseif ($state==='pending'): ?><span class="attendance-state">รอแอดมินอนุมัติ · ยังไม่ได้รับชั่วโมง</span>
<?php elseif ($state==='rejected'): ?><span class="attendance-state rejected">ไม่อนุมัติ · ยังไม่ได้รับชั่วโมง</span>
<?php elseif ($state==='awaiting_evidence'): ?><span class="attendance-state">ยืนยันรหัสแล้ว · รอส่งรูปหลักฐาน</span>
<?php elseif ($now<$ev['start_datetime']): ?><span class="attendance-state muted">ยังไม่ถึงเวลาเช็กอิน</span>
<?php elseif ($now>=$ev['end_datetime']): ?><span class="attendance-state muted">หมดเวลาเช็กอิน</span>
<?php else: ?><div class="checkin-form"><input type="text" class="checkin-code-input" placeholder="กรอกรหัส" maxlength="10" aria-label="รหัสเช็กอิน <?= $h($ev['title']) ?>"><button type="button" class="checkin-confirm" onclick="submitCheckin(<?= (int)$ev['id'] ?>,this)">ยืนยัน</button><button type="button" class="checkin-scan-button" data-event-id="<?= (int)$ev['id'] ?>" aria-controls="qrScannerBox-<?= (int)$ev['id'] ?>" aria-expanded="false">📷 สแกน QR Code</button></div><div class="checkin-msg" role="status"></div><div id="qrScannerBox-<?= (int)$ev['id'] ?>" class="checkin-scanner-box" hidden></div>
<?php endif; ?></div></div>
<?php if ($state==='rejected'): ?><p class="attendance-note">เหตุผลจากแอดมิน: <?= $h($ev['review_note']) ?></p><?php endif; ?>
<?php if (!$approved && in_array($state,['awaiting_evidence','rejected'],true)):
 if ($now<=$deadline): ?>
<form method="POST" enctype="application/x-www-form-urlencoded" class="attendance-evidence-form">
<?= csrfField() ?><input type="hidden" name="registration_id" value="<?= (int)$ev['registration_id'] ?>"><input type="hidden" name="evidence_data" value="">
<label for="proof-<?= (int)$ev['id'] ?>">รูปหลักฐานการเข้าร่วม (จำเป็น)</label><input type="file" id="proof-<?= (int)$ev['id'] ?>" class="evidence-file" accept="image/jpeg,image/png,image/webp" required>
<p class="attendance-meta">JPG, PNG หรือ WEBP ไม่เกิน 5 MB ระบบจะย่อรูปก่อนส่ง</p>
<img class="evidence-preview" hidden alt="ตัวอย่างรูปหลักฐาน">
<label for="caption-<?= (int)$ev['id'] ?>">คำอธิบายหลักฐาน (ไม่บังคับ)</label><textarea id="caption-<?= (int)$ev['id'] ?>" name="caption" maxlength="500"></textarea>
<p class="evidence-error" role="status"></p><button type="submit" class="btn"><?= $state==='rejected' ? 'ส่งหลักฐานใหม่ให้แอดมินตรวจ' : 'ส่งหลักฐานเพื่อขออนุมัติชั่วโมง' ?></button></form>
<?php else: ?><p class="attendance-note">หมดเวลาส่งหลักฐานแล้ว กรุณาติดต่อผู้ดูแลระบบ</p><?php endif; endif; ?>
<?php if (!empty($ev['evidence_filename'])): ?><div class="attendance-proof"><p>หลักฐานที่ส่งล่าสุด</p><a href="api/attendance-evidence.php?id=<?= (int)$ev['review_id'] ?>" target="_blank" rel="noopener"><img src="api/attendance-evidence.php?id=<?= (int)$ev['review_id'] ?>" alt="รูปหลักฐานที่ส่งให้แอดมินตรวจ"></a><?php if ($state==='pending'): ?><p class="attendance-note">ระหว่างรอตรวจจะยังไม่เพิ่มชั่วโมง และยังแก้หลักฐานชุดนี้ไม่ได้</p><?php endif; ?></div><?php endif; ?>
</article><?php endforeach; ?></section></div></main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
async function submitCheckin(eventId,button){
 if(button.disabled)return;const row=button.closest('.checkin-item'),input=row.querySelector('.checkin-code-input'),msg=row.querySelector('.checkin-msg');
 if(!input.value.trim()){msg.textContent='กรุณากรอกรหัส';return;}button.disabled=true;msg.textContent='กำลังตรวจสอบรหัส...';
 try{const response=await fetch('api/checkin.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':<?= json_encode($_SESSION['csrf_token']) ?>},body:JSON.stringify({event_id:eventId,code:input.value.trim()})});const result=await response.json();
 const messages={awaiting_evidence:'ยืนยันรหัสแล้ว กรุณาส่งรูปหลักฐานเพื่อรอแอดมินอนุมัติ',pending_approval:'ส่งหลักฐานแล้ว รอแอดมินอนุมัติ',already_checked_in:'กิจกรรมนี้ได้รับชั่วโมงแล้ว',wrong_code:'รหัสไม่ถูกต้อง',not_started:'กิจกรรมยังไม่เริ่ม',event_ended:'กิจกรรมสิ้นสุดแล้ว',not_registered:'คุณยังไม่ได้ลงทะเบียนกิจกรรมนี้',rate_limited:'ลองหลายครั้งเกินไป กรุณารอ 15 นาที',forbidden:'เฉพาะบัญชีนักศึกษาเท่านั้น'};
 msg.textContent=messages[result.result]||'ตรวจสอบไม่สำเร็จ กรุณาลองใหม่';
 if(['awaiting_evidence','pending_approval','already_checked_in'].includes(result.result))setTimeout(()=>location.reload(),400);
 }catch{msg.textContent='เชื่อมต่อไม่สำเร็จ กรุณาลองใหม่';}finally{button.disabled=false;}
}
<?php if ($autoEventId>0 && $autoCode!==''): ?>
window.addEventListener('DOMContentLoaded',()=>{const button=document.querySelector('button[onclick="submitCheckin(<?= $autoEventId ?>,this)"]');if(button){button.closest('.checkin-item').querySelector('.checkin-code-input').value=<?= json_encode($autoCode) ?>;void submitCheckin(<?= $autoEventId ?>,button);}});
<?php endif; ?>
</script>
<script src="js/checkin-scanner.js?v=<?= filemtime(__DIR__.'/js/checkin-scanner.js') ?>" defer></script>
<script src="js/attendance-evidence.js?v=<?= filemtime(__DIR__.'/js/attendance-evidence.js') ?>" defer></script>
<script src="js/mobile-layout.js?v=<?= filemtime(__DIR__.'/js/mobile-layout.js') ?>"></script>
</body></html>
