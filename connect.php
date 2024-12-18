<?php
// connect.php

// ใช้ environment variables สำหรับข้อมูลที่สำคัญ
$servername = getenv('DB_SERVER') ?: '202.28.34.205';
$username = getenv('DB_USERNAME') ?: '64011211132';
$password = getenv('DB_PASSWORD') ?: '64011211132';
$dbname = getenv('DB_NAME') ?: 'db64011211132';

// สร้างการเชื่อมต่อใหม่ด้วย mysqli
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    error_log("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
    die("ขออภัย ระบบมีปัญหา กรุณาลองใหม่อีกครั้งในภายหลัง");
}

// ตั้งค่า charset ให้รองรับ utf8mb4
$conn->set_charset("utf8mb4");
?>
