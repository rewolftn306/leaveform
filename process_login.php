<?php
// process_login.php
session_start();
include('connect.php');

// ตรวจสอบว่ามีการส่งฟอร์มผ่าน POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์มและกรองข้อมูล
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // ตรวจสอบว่ากรอกข้อมูลครบถ้วนหรือไม่
    if (empty($username) || empty($password)) {
        header("Location: login.php?error=กรุณากรอกชื่อผู้ใช้และรหัสผ่าน");
        exit();
    }

    // เตรียมคำสั่ง SQL เพื่อค้นหาผู้ใช้
    $stmt = $conn->prepare("SELECT UserID, Username, Password, FirstName, LastName, Role FROM users WHERE Username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        // ตรวจสอบว่าพบผู้ใช้หรือไม่
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($userID, $dbUsername, $dbPassword, $firstName, $lastName, $role);
            $stmt->fetch();

            // ตรวจสอบรหัสผ่าน
            if (password_verify($password, $dbPassword)) {
                // ตั้งค่าเซสชัน
                $_SESSION['UserID'] = $userID;
                $_SESSION['Username'] = $dbUsername;
                $_SESSION['FirstName'] = $firstName;
                $_SESSION['LastName'] = $lastName;
                $_SESSION['Role'] = $role;

                // รีไดเรกต์ไปยังหน้าแรก
                header("Location: index.php?success=เข้าสู่ระบบสำเร็จ");
                exit();
            } else {
                header("Location: login.php?error=รหัสผ่านไม่ถูกต้อง");
                exit();
            }
        } else {
            header("Location: login.php?error=ไม่พบผู้ใช้ที่มีชื่อผู้ใช้นี้");
            exit();
        }

        $stmt->close();
    } else {
        // กรณีเกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL
        header("Location: login.php?error=เกิดข้อผิดพลาดในการประมวลผล");
        exit();
    }
}

$conn->close();
?>
