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

// ดึงข้อมูลผู้ใช้ก่อนทำการลบ
$stmt_fetch = $conn->prepare("SELECT profile_picture, Role FROM users WHERE UserID = ?");
$stmt_fetch->bind_param("i", $userID);
$stmt_fetch->execute();
$stmt_fetch->bind_result($profile_picture, $role);
$stmt_fetch->fetch();
$stmt_fetch->close();

// ตรวจสอบว่าผู้ใช้มีบทบาทที่สามารถลบได้หรือไม่
$protected_roles = ['Admin'];
if (in_array($role, $protected_roles)) {
    header("Location: manage_users.php?error=ไม่สามารถลบผู้ใช้งานที่มีบทบาทสูงได้");
    exit;
}

// เริ่มต้น Transaction
$conn->begin_transaction();

try {
    // ลบข้อมูลจากตาราง approvers ถ้ามี
    $stmt_approvers = $conn->prepare("DELETE FROM approvers WHERE UserID = ?");
    $stmt_approvers->bind_param("i", $userID);
    if (!$stmt_approvers->execute()) {
        throw new Exception("ลบ approvers ล้มเหลว: " . $stmt_approvers->error);
    }
    $stmt_approvers->close();

    // ลบผู้ใช้จากตาราง users
    $stmt_user = $conn->prepare("DELETE FROM users WHERE UserID = ?");
    $stmt_user->bind_param("i", $userID);
    if (!$stmt_user->execute()) {
        throw new Exception("ลบผู้ใช้งานไม่สำเร็จ: " . $stmt_user->error);
    }
    $stmt_user->close();

    // Commit Transaction
    $conn->commit();

    // ลบไฟล์โปรไฟล์ถ้ามี
    if (!empty($profile_picture) && file_exists($profile_picture)) {
        unlink($profile_picture);
    }

    header("Location: manage_users.php?success=ลบผู้ใช้งานสำเร็จ");
    exit;
} catch (Exception $e) {
    // Rollback Transaction ในกรณีที่เกิดข้อผิดพลาด
    $conn->rollback();
    error_log($e->getMessage());
    header("Location: manage_users.php?error=ลบผู้ใช้งานไม่สำเร็จ");
    exit;
}

$conn->close();
?>