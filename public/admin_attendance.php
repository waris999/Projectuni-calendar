<?php
require_once __DIR__.'/../src/security.php';
if (!isset($_SESSION['user_id'])) { header('Location: index.php');exit; }
require_once __DIR__.'/../config/database.php';
$role=$pdo->prepare('SELECT role FROM users WHERE id=?');$role->execute([$_SESSION['user_id']]);
if ($role->fetchColumn()!=='admin') { http_response_code(403);exit('เฉพาะผู้ดูแลระบบเท่านั้น'); }
require_once __DIR__.'/../src/AttendanceReview.php';
require_once __DIR__.'/../src/Avatar.php';
$avatarSrc=currentAvatarUrl($pdo,(int)$_SESSION['user_id']);$error='';
$messages=['invalid_note'=>'กรุณาระบุเหตุผลเมื่อไม่อนุมัติ และใช้ข้อความไม่เกิน 500 ตัวอักษร','already_reviewed'=>'รายการนี้ไม่ได้อยู่ในสถานะรออนุมัติแล้ว กรุณาตรวจสอบรายการล่าสุด','missing_evidence'=>'ไม่พบไฟล์รูปหลักฐาน จึงยังอนุมัติไม่ได้','forbidden'=>'เฉพาะแอดมินเท่านั้น','not_found'=>'ไม่พบคำขอนี้'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $id=filter_var($_POST['registration_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $decision=is_string($_POST['decision'] ?? null) ? $_POST['decision'] : '';$note=is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
 try{$result=$id ? (new AttendanceReview($pdo))->decide((int)$_SESSION['user_id'],$id,$decision,$note) : 'not_found';
 if(in_array($result,['approved','rejected'],true)){header('Location: admin_attendance.php?done='.$result);exit;}
 $error=$messages[$result] ?? 'บันทึกผลไม่สำเร็จ';
 }catch(Throwable $e){error_log('Attendance review failed: '.get_class($e));$error='บันทึกผลไม่สำเร็จ กรุณาลองใหม่';}
}
$filter=is_string($_GET['status'] ?? null) ? $_GET['status'] : 'pending';
if(!in_array($filter,['pending','approved','rejected','awaiting_evidence','all'],true))$filter='pending';
$search=is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$page=max(1,(int)($_GET['page'] ?? 1));$where=[];$params=[];
if($filter!=='all'){$where[]='a.status=?';$params[]=$filter;}
if($search!==''){$where[]='(u.username LIKE ? OR u.fullname LIKE ? OR e.title LIKE ?)';array_push($params,'%'.$search.'%','%'.$search.'%','%'.$search.'%');}
$base=' FROM attendance_reviews a JOIN registrations r ON r.id=a.registration_id JOIN users u ON u.id=r.user_id JOIN events e ON e.id=r.event_id';
if($where)$base.=' WHERE '.implode(' AND ',$where);
$s=$pdo->prepare('SELECT COUNT(*)'.$base);$s->execute($params);$total=(int)$s->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);$offset=($page-1)*20;
$s=$pdo->prepare('SELECT a.*,r.user_id,u.username,u.fullname,u.student_year,e.title,e.activity_hours,e.start_datetime'.$base.' ORDER BY a.submitted_at DESC,a.id DESC LIMIT 20 OFFSET '.$offset);$s->execute($params);$items=$s->fetchAll();
$h=static fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
$labels=['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ','awaiting_evidence'=>'รอนักศึกษาส่งรูป','all'=>'ทั้งหมด'];
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>อนุมัติชั่วโมงกิจกรรม | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__.'/css/style.css') ?>"><link rel="stylesheet" href="css/attendance.css?v=<?= filemtime(__DIR__.'/css/attendance.css') ?>"></head><body class="app-layout">
<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="รูปโปรไฟล์" width="70" height="70" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E';"></a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">เจ้าหน้าที่</div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
        <a href="admin_hours.php">⏱️ ชั่วโมงกิจกรรม</a>
            <a href="admin_attendance.php" class="active">📝 อนุมัติชั่วโมง</a>
        <a href="admin_users.php">👥 จัดการผู้ใช้</a>
        <a href="notifications.php">🔔 แจ้งเตือน</a>
        <form method="POST" action="logout.php" class="logout-form" onsubmit="return confirm('ยืนยันออกจากระบบหรือไม่?');">
            <?= csrfField() ?>
            <button type="submit" class="logout-button">🚪 ออกจากระบบ</button>
        </form>
    </nav>
</aside>
<main class="main"><div class="topbar">Home / อนุมัติชั่วโมงกิจกรรม</div><div class="content">
<?php if($error): ?><p class="attendance-error" role="alert"><?= $h($error) ?></p><?php endif; ?>
<?php if(in_array($_GET['done'] ?? '',['approved','rejected'],true)): ?><p class="attendance-success" role="status">บันทึกผลแล้ว และแจ้งผลให้นักศึกษาแล้ว</p><?php endif; ?>
<section class="card"><h2>ตรวจหลักฐานและอนุมัติชั่วโมงกิจกรรม</h2><p class="attendance-intro">ตรวจสอบชื่อกิจกรรม ผู้ส่ง และรูปหลักฐานก่อนตัดสินใจ ชั่วโมงจะเพิ่มเฉพาะเมื่อแอดมินกดอนุมัติ</p>
<form method="GET" class="review-filters"><input name="q" value="<?= $h($search) ?>" placeholder="ค้นหาชื่อ รหัสนักศึกษา หรือกิจกรรม" aria-label="ค้นหาคำขอ"><select name="status" aria-label="สถานะคำขอ"><?php foreach($labels as $value=>$label): ?><option value="<?= $value ?>" <?= $filter===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select><button type="submit" class="btn">ค้นหา</button></form>
<p class="attendance-meta">พบ <?= $total ?> รายการ · หน้า <?= $page ?>/<?= $pages ?></p>
<?php if(!$items): ?><p class="attendance-note">ไม่มีรายการตามเงื่อนไขที่เลือก</p><?php endif; ?>
<?php foreach($items as $item): ?><article class="attendance-row"><div class="attendance-row-head"><div><h3><?= $h($item['title']) ?></h3><p class="attendance-meta"><?= $h($item['fullname']) ?> · <?= $h($item['username']) ?> · ชั้นปี <?= (int)$item['student_year'] ?><br>ชั่วโมงกิจกรรม <?= $h($item['activity_hours']) ?> ชม. · ส่งหลักฐาน <?= $h($item['submitted_at'] ?: 'ยังไม่ได้ส่ง') ?></p></div><span class="attendance-state <?= $h($item['status']) ?>"><?= $h($labels[$item['status']]) ?></span></div>
<?php if($item['evidence_filename']): ?><div class="attendance-proof"><a href="api/attendance-evidence.php?id=<?= (int)$item['id'] ?>" target="_blank" rel="noopener"><img src="api/attendance-evidence.php?id=<?= (int)$item['id'] ?>" alt="หลักฐานของ <?= $h($item['fullname']) ?>"><span>เปิดรูปขนาดเต็ม</span></a><p class="attendance-note"><?= nl2br($h($item['evidence_caption'])) ?></p></div><?php endif; ?>
<?php if($item['status']==='pending'): ?><form method="POST" class="review-decision" onsubmit="return confirm('ยืนยันบันทึกผลการตรวจหลักฐานของนักศึกษาคนนี้?');"><?= csrfField() ?><input type="hidden" name="registration_id" value="<?= (int)$item['registration_id'] ?>"><label for="note-<?= (int)$item['id'] ?>">หมายเหตุหรือเหตุผล (ต้องระบุเมื่อไม่อนุมัติ)</label><textarea id="note-<?= (int)$item['id'] ?>" name="note" maxlength="500"></textarea><div class="review-actions"><button type="submit" class="btn" name="decision" value="approve">✓ อนุมัติและเพิ่มชั่วโมง</button><button type="submit" class="btn btn-reject" name="decision" value="reject">ไม่อนุมัติ</button></div></form>
<?php elseif($item['reviewed_at']): ?><p class="attendance-note">ตรวจเมื่อ <?= $h($item['reviewed_at']) ?><?php if($item['review_note']): ?><br>หมายเหตุ: <?= $h($item['review_note']) ?><?php endif; ?></p><?php endif; ?>
</article><?php endforeach; ?>
<nav class="review-pagination" aria-label="หน้ารายการ"><?php if($page>1): ?><a href="?<?= $h(http_build_query(['status'=>$filter,'q'=>$search,'page'=>$page-1])) ?>">← ก่อนหน้า</a><?php endif; ?><?php if($page<$pages): ?><a href="?<?= $h(http_build_query(['status'=>$filter,'q'=>$search,'page'=>$page+1])) ?>">ถัดไป →</a><?php endif; ?></nav></section></div></main><script src="js/mobile-layout.js?v=<?= filemtime(__DIR__.'/js/mobile-layout.js') ?>"></script></body></html>
