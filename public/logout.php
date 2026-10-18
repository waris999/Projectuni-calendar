<?php
require_once __DIR__ . '/../src/security.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php'));
    exit;
}
session_unset();
if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>$p['path'],'secure'=>$p['secure'],'httponly'=>true,'samesite'=>'Lax']); }
session_destroy();
header('Location: index.php'); exit;
