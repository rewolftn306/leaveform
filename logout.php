<?php
session_start();

// ลบข้อมูล session
session_unset();
session_destroy();

// เปลี่ยนหน้าไปที่หน้า login
header("Location: login.php");
exit();
?>
