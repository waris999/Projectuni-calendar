<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/User.php';

$userModel = new User($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $deleteId = (int) $_POST['delete_user_id'];
    if ($deleteId !== (int) $_SESSION['user_id']) {
        $userModel->deleteUser($deleteId);
    }
    header('Location: admin_users.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$yearFilter = $_GET['year'] ?? '';
$users = $userModel->getAll($search, $roleFilter, $yearFilter);

$avatarSrc = !empty($_SESSION['profile_image'])
    ? 'img/avatars/' . htmlspecialchars($_SESSION['profile_image'])
    : 'img/avatar-placeholder.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการผู้ใช้ | Uni Calendar</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="app-layout">

<aside class="sidebar">
    <div class="profile">
        <a href="profile.php"><img src="<?= $avatarSrc ?>" alt="avatar"></a>
        <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
        <div class="role">เจ้าหน้าที่</div>
    </div>
    <nav>
        <a href="dashboard.php">🏠 หน้าหลัก</a>
        <a href="calendar.php">📅 ปฏิทินกิจกรรม</a>
        <a href="admin_dashboard.php">📊 แดชบอร์ด</a>
        <a href="admin_users.php" class="active">👥 จัดการผู้ใช้</a>
        <a href="notifications.php">🔔 แจ้งเตือน</a>
        <a href="logout.php">🚪 ออกจากระบบ</a>
    </nav>
</aside>

<div class="main">
    <div class="topbar">Home / จัดการผู้ใช้</div>
    <div class="content">
        <div class="card">
            <h2>ผู้ใช้งานทั้งหมด (<?= count($users) ?> คน)</h2>

            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="ค้นหาชื่อ/รหัสนักศึกษา" value="<?= htmlspecialchars($search) ?>">
                <select name="role">
                    <option value="">ทุกสถานะ</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>นักศึกษา</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>เจ้าหน้าที่</option>
                </select>
                <select name="year">
                    <option value="">ทุกชั้นปี</option>
                    <option value="1" <?= $yearFilter === '1' ? 'selected' : '' ?>>ปี 1</option>
                    <option value="2" <?= $yearFilter === '2' ? 'selected' : '' ?>>ปี 2</option>
                    <option value="3" <?= $yearFilter === '3' ? 'selected' : '' ?>>ปี 3</option>
                    <option value="4" <?= $yearFilter === '4' ? 'selected' : '' ?>>ปี 4</option>
                </select>
                <button type="submit" class="btn">ค้นหา</button>
            </form>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ชื่อ-นามสกุล</th><th>รหัส/Username</th><th>สถานะ</th><th>ชั้นปี</th><th>ลงทะเบียนแล้ว</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['fullname']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= $u['role'] === 'admin' ? 'เจ้าหน้าที่' : 'นักศึกษา' ?></td>
                            <td><?= $u['student_year'] ? 'ปี ' . (int) $u['student_year'] : '-' ?></td>
                            <td><?= (int) $u['reg_count'] ?></td>
                            <td>
                                <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                                    <form method="POST" onsubmit="return confirm('ยืนยันการลบผู้ใช้นี้? ข้อมูลลงทะเบียนทั้งหมดจะถูกลบไปด้วย');" style="display:inline;">
                                        <input type="hidden" name="delete_user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" style="background:none; border:none; color:#c0392b; cursor:pointer; font-size:13px;">ลบ</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>