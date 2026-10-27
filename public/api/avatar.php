<?php
require_once __DIR__.'/../../src/security.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../src/Avatar.php';
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
$user=avatarAccount($pdo,(int)$_SESSION['user_id']);
if (!$user) { http_response_code(401); exit; }
$file=avatarFilePath($user['profile_image'] ?? null);
$info=$file ? @getimagesize($file) : false; $mime=$info['mime'] ?? '';
$valid=$file!==null && in_array($mime,['image/jpeg','image/png','image/webp'],true);
header('Cache-Control: private, no-store');
if (($_GET['status'] ?? '')==='1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['has_profile_image'=>!empty($user['profile_image']),'file_exists'=>$file!==null,'image_valid'=>$valid]);exit;
}
if (!$valid) { header('Content-Type: image/svg+xml; charset=utf-8');echo defaultAvatarSvg();exit; }
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));
session_write_close();readfile($file);
