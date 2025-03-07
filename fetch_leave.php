<?php
// เปิดการแสดงข้อผิดพลาดสำหรับการพัฒนา (ปิดเมื่อใช้งานจริง)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// เชื่อมต่อฐานข้อมูล
require_once 'connect.php';

if (!$conn) {
    die(json_encode(["error" => "Database connection failed: " . mysqli_connect_error()]));
}

// กำหนด Content-Type ให้เป็น JSON
header('Content-Type: application/json');

// ตรวจสอบว่า UserID ถูกส่งมาในคำขอหรือไม่
if (!isset($_GET['UserID'])) {
    die(json_encode(["error" => "UserID is required"]));
}

// ดึงค่า UserID และตรวจสอบความถูกต้อง
$userId = intval($_GET['UserID']);

if ($userId <= 0) {
    die(json_encode(["error" => "Invalid UserID"]));
}

// เตรียมคำสั่ง SQL
$sql = "SELECT LeaveTypeID, StartDate, EndDate, ApprovalStatus FROM leaveapplications WHERE UserID = ?";
$stmt = $conn->prepare($sql);

// ตรวจสอบว่าการเตรียมคำสั่ง SQL สำเร็จหรือไม่
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    // เก็บผลลัพธ์
    $leaveData = [];
    while ($row = $result->fetch_assoc()) {
        $leaveData[] = $row;
    }

    // ส่งผลลัพธ์ JSON
    echo json_encode($leaveData, JSON_PRETTY_PRINT);
} else {
    // ข้อผิดพลาดในการเตรียมคำสั่ง SQL
    echo json_encode(["error" => "Failed to prepare statement: " . $conn->error]);
}

// ปิดคำสั่งและการเชื่อมต่อ
if ($stmt) {
    $stmt->close();
}
$conn->close();
?>