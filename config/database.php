<?php
// config/database.php
// ไฟล์เชื่อมต่อฐานข้อมูลด้วย PDO

$host = '127.0.0.1';
$dbname = 'uni-calendar';   // ชื่อ database ตามที่สร้างไว้ใน phpMyAdmin
$username = 'root';         // ค่า default ของ XAMPP
$password = '';             // ค่า default ของ XAMPP คือรหัสว่าง
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ถ้า query error จะ throw exception ให้เห็น
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // ดึงข้อมูลมาเป็น array แบบ key=>value
    PDO::ATTR_EMULATE_PREPARES   => false,                   // ใช้ prepared statement จริงของ MySQL (ปลอดภัยกว่า)
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // ห้ามโชว์ error ตรงๆ บน production แต่ตอน dev โชว์ไว้ก่อนเพื่อ debug ง่าย
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage());
}