<?php
// logout.php
session_start();

// ลบข้อมูลทั้งหมดใน Session
$_SESSION = [];

// ล้างค่า Session Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย Session
session_destroy();

// รีไดเรกต์ไปยังหน้าเข้าสู่ระบบพร้อมข้อความ
header("Location: login.php?message=คุณได้ออกจากระบบเรียบร้อยแล้ว");
exit();
?>
