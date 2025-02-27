<?php
// delete_user.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบบทบาทของผู้ใช้
if ($_SESSION['Role'] !== 'Admin') {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

include('connect.php');

// ตรวจสอบว่ามีการส่ง parameter `userid` มาหรือไม่
if (!isset($_GET['userid'])) {
    error_log("Error: ไม่มีการส่ง parameter 'userid'");
    header("Location: manage_users.php?error=ไม่พบผู้ใช้งาน");
    exit;
}

$userID = intval($_GET['userid']);

// ตรวจสอบค่าที่ได้รับ
error_log("Received UserID: " . $userID);

// เริ่มต้น Transaction
$conn->begin_transaction();

try {
    // ลบผู้ใช้จากตาราง users
    $stmt_user = $conn->prepare("DELETE FROM users WHERE UserID = ?");
    $stmt_user->bind_param("i", $userID);

    // ตรวจสอบว่า prepare ทำงานหรือไม่
    if ($stmt_user === false) {
        error_log("Error preparing SQL: " . $conn->error);
        throw new Exception("ไม่สามารถเตรียมคำสั่ง SQL ได้");
    }

    if (!$stmt_user->execute()) {
        error_log("Execute Error: ลบผู้ใช้งานไม่สำเร็จ: " . $stmt_user->error);
        throw new Exception("ลบผู้ใช้งานไม่สำเร็จ: " . $stmt_user->error);
    }

    $stmt_user->close();

    // Commit Transaction
    $conn->commit();
    error_log("UserID $userID ลบผู้ใช้งานสำเร็จ");

    header("Location: manage_users.php?success=ลบผู้ใช้งานสำเร็จ");
    exit;
} catch (Exception $e) {
    // Rollback Transaction ในกรณีที่เกิดข้อผิดพลาด
    $conn->rollback();
    error_log("ข้อผิดพลาด: " . $e->getMessage());
    header("Location: manage_users.php?error=ลบผู้ใช้งานไม่สำเร็จ: " . $e->getMessage());
    exit;
}

$conn->close();
?>
