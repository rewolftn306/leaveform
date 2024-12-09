<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";  // ชื่อเซิร์ฟเวอร์ฐานข้อมูล (อาจเป็น localhost หรือ IP ของเซิร์ฟเวอร์)
$username = "root";         // ชื่อผู้ใช้ฐานข้อมูล
$password = "1";             // รหัสผ่านฐานข้อมูล
$dbname = "leaveform"; // ชื่อฐานข้อมูล

// สร้างการเชื่อมต่อ
$mysqli = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($mysqli->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $mysqli->connect_error);
}
?>
