<?php
require_once __DIR__ . '/app.php';
// config/database.php
// ไฟล์เชื่อมต่อฐานข้อมูลด้วย PDO

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'uni-calendar';   // ชื่อ database ตามที่สร้างไว้ใน phpMyAdmin
$username = getenv('DB_USER') ?: 'root';         // ค่า default ของ XAMPP
$password = getenv('DB_PASSWORD') ?: '';             // ค่า default ของ XAMPP คือรหัสว่าง
$charset = 'utf8mb4';

$port = getenv('DB_PORT') ?: '3306';
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ถ้า query error จะ throw exception ให้เห็น
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // ดึงข้อมูลมาเป็น array แบบ key=>value
    PDO::ATTR_EMULATE_PREPARES   => false,                   // ใช้ prepared statement จริงของ MySQL (ปลอดภัยกว่า)
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    // ห้ามโชว์ error ตรงๆ บน production แต่ตอน dev โชว์ไว้ก่อนเพื่อ debug ง่าย
    error_log('Database connection failed');
    http_response_code(503);
    die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้');
}
