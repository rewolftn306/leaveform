<?php
session_start();

// รวมไฟล์เชื่อมต่อฐานข้อมูล
include('connect.php');  // เชื่อมต่อกับไฟล์ connect.php ที่สร้างขึ้น

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        header("Location: login.php?error=กรุณากรอกชื่อผู้ใช้และรหัสผ่าน");
        exit;
    }

    // ตรวจสอบข้อมูลในฐานข้อมูล
    $stmt = $conn->prepare("SELECT UserID, Username, Password, Role FROM users WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['Password'])) {
            $_SESSION['UserID'] = $user['UserID'];
            $_SESSION['Username'] = $user['Username'];
            $_SESSION['Role'] = $user['Role'];
            header("Location: index.php");
        } else {
            header("Location: login.php?error=รหัสผ่านไม่ถูกต้อง");
        }
    } else {
        header("Location: login.php?error=ไม่พบผู้ใช้งานนี้");
    }

    $stmt->close();
}

$conn->close();
?>
