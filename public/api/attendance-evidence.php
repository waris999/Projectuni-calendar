<?php
require_once __DIR__.'/../../src/security.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../src/AttendanceReview.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401);exit; }
$id=filter_var($_GET['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) { http_response_code(404);exit; }
$s=$pdo->prepare('SELECT a.evidence_filename,r.user_id,u.role FROM attendance_reviews a JOIN registrations r ON r.id=a.registration_id JOIN users u ON u.id=? WHERE a.id=?');
$s->execute([$_SESSION['user_id'],$id]);$row=$s->fetch();
if (!$row || ((int)$row['user_id']!==(int)$_SESSION['user_id'] && $row['role']!=='admin')) { http_response_code(404);exit; }
$file=AttendanceReview::evidenceFile($row['evidence_filename']);
if (!$file) { http_response_code(404);exit; }
header('Content-Type: image/jpeg');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($file));
session_write_close();readfile($file);
