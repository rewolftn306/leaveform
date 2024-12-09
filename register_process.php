<?php
// เชื่อมต่อฐานข้อมูล
include('connect.php');

// รับข้อมูลจากฟอร์ม
$username = $_POST['username'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสรหัสผ่าน
$firstname = $_POST['firstname'];
$lastname = $_POST['lastname'];
$email = $_POST['email'];
$role = $_POST['role'];
$position = $_POST['position'];
$department = $_POST['department'];
$tel = $_POST['tel'];
$profile_picture = $_FILES['profile_picture']['name']; // รับชื่อไฟล์โปรไฟล์
$target_dir = "uploads/"; // โฟลเดอร์ที่เก็บโปรไฟล์
$target_file = $target_dir . basename($_FILES['profile_picture']['name']);

// 1. Insert ข้อมูลลงในตาราง `users`
$sql_users = "INSERT INTO users (username, password, firstname, lastname, email, role, profile_picture)
              VALUES ('$username', '$password', '$firstname', '$lastname', '$email', '$role', '$profile_picture')";

if ($mysqli->query($sql_users) === TRUE) {
    // 2. ดึง `UserID` ที่เพิ่งเพิ่มจากตาราง `users`
    $user_id = $mysqli->insert_id; // รหัสผู้ใช้ที่เพิ่งเพิ่ม

    // 3. Insert ข้อมูลลงในตาราง `employees` สำหรับ role = Employee
    if ($role == "Employee") {
        $sql_employees = "INSERT INTO employees (UserID, Position, Department, StartOfWork, Email, Tel, Name, profile_picture, role)
                          VALUES ('$user_id', '$position', '$department', NOW(), '$email', '$tel', '$firstname $lastname', '$profile_picture', '$role')";

        // Execute การแทรกข้อมูลลงในตาราง `employees`
        if ($mysqli->query($sql_employees) === TRUE) {
            // ย้ายไฟล์โปรไฟล์
            move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file);
            
            // แสดงข้อความว่า "ลงทะเบียนผู้ใช้และข้อมูลพนักงานเสร็จสมบูรณ์"
            echo "<script>
                    alert('ลงทะเบียนผู้ใช้และข้อมูลพนักงานเสร็จสมบูรณ์');
                    window.location.href = 'login.php';
                  </script>";
        } else {
            echo "เกิดข้อผิดพลาดในการบันทึกข้อมูลพนักงาน: " . $mysqli->error;
        }
    }
} else {
    echo "เกิดข้อผิดพลาดในการบันทึกข้อมูลผู้ใช้: " . $mysqli->error;
}
?>
