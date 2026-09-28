<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: index.php?error=empty');
    exit;
}

$userModel = new User($pdo);
$result = $userModel->login($username, $password);

if ($result === 'invalid') {
    header('Location: index.php?error=invalid');
    exit;
}

if ($result === 'locked') {
    header('Location: index.php?error=locked');
    exit;
}

// login สำเร็จ ($result เป็น array ข้อมูล user)
$_SESSION['user_id']       = $result['id'];
$_SESSION['username']      = $result['username'];
$_SESSION['fullname']      = $result['fullname'];
$_SESSION['role']          = $result['role'];
$_SESSION['student_year']  = $result['student_year'];
$_SESSION['profile_image'] = $result['profile_image'];

header('Location: dashboard.php');
exit;