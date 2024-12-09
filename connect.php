<?php
// เชื่อมต่อฐานข้อมูล
$servername = "202.28.34.205";
$username = "64011211132";
$password = "64011211132";
$dbname = "db64011211132";  // ชื่อฐานข้อมูลที่ใช้

$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}
?>
