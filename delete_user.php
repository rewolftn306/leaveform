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
    header("Location: manage_users.php?error=ไม่พบผู้ใช้งาน");
    exit;
}

$userID = intval($_GET['userid']);

// ตรวจสอบว่าผู้ใช้งานนั้นไม่ใช่ Admin ตัวเอง
if ($userID === $_SESSION['UserID']) {
    header("Location: manage_users.php?error=คุณไม่สามารถลบบัญชีของคุณเองได้");
    exit;
}

// ลบข้อมูลจากตาราง approvers ถ้ามี
$stmt_approvers = $conn->prepare("DELETE FROM approvers WHERE UserID = ?");
$stmt_approvers->bind_param("i", $userID);
$stmt_approvers->execute();
$stmt_approvers->close();

// ลบผู้ใช้จากตาราง users
$stmt_user = $conn->prepare("DELETE FROM users WHERE UserID = ?");
$stmt_user->bind_param("i", $userID);
if ($stmt_user->execute()) {
    $stmt_user->close();
    header("Location: manage_users.php?success=ลบผู้ใช้งานสำเร็จ");
    exit;
} else {
    $stmt_user->close();
    header("Location: manage_users.php?error=ลบผู้ใช้งานไม่สำเร็จ");
    exit;
}

$conn->close();
?>
