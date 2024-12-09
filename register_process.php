<?php
// รวมไฟล์เชื่อมต่อฐานข้อมูล
include('connect.php'); // ตรวจสอบว่า path ถูกต้อง

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!$conn) {
    die("ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
}

// รับค่าจากฟอร์ม
$username = $_POST['username'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสรหัสผ่าน
$firstname = $_POST['firstname'] ? $_POST['firstname'] : NULL; // กรณีที่ไม่ได้กรอกให้เป็น NULL
$lastname = $_POST['lastname'] ? $_POST['lastname'] : NULL; // กรณีที่ไม่ได้กรอกให้เป็น NULL
$email = $_POST['email'] ? $_POST['email'] : NULL; // กรณีที่ไม่ได้กรอกให้เป็น NULL
$role = $_POST['role'] ? $_POST['role'] : NULL; // กรณีที่ไม่ได้กรอกให้เป็น NULL

// การอัปโหลดไฟล์โปรไฟล์
$profile_picture = NULL;
if (!empty($_FILES["profile_picture"]["name"])) {
    $target_dir = "uploads/"; // โฟลเดอร์ที่เก็บไฟล์
    $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
    $profile_picture = $target_file;
    if (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
        die("ขออภัย มีข้อผิดพลาดในการอัปโหลดไฟล์.");
    }
}

// สร้างคำสั่ง SQL เพื่อบันทึกข้อมูลลงในตาราง users
$sql_users = "INSERT INTO users (username, password, firstname, lastname, email, role, profile_picture) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";

// เตรียมคำสั่ง SQL
$stmt_users = $conn->prepare($sql_users);
if (!$stmt_users) {
    die("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error);
}

// ผูกค่าตัวแปรกับคำสั่ง SQL
$stmt_users->bind_param("sssssss", $username, $password, $firstname, $lastname, $email, $role, $profile_picture);

// ประมวลผลคำสั่ง SQL
if ($stmt_users->execute()) {
    echo "ลงทะเบียนสำเร็จ!";

    // ตรวจสอบว่า role เป็น Employee หรือไม่
    if ($role == "Employee") {
        // ดึงข้อมูลจากตาราง users ไปยังตาราง employees
        $last_inserted_id = $stmt_users->insert_id;

        // กำหนดค่า Tel และ Department สำหรับ Employee
        $tel = NULL;  // สามารถใส่ค่าตามที่ต้องการได้ เช่น 'N/A'
        $department = 'พนักงาน';  // หรือสามารถกำหนดตามตำแหน่ง เช่น 'Admin' หรือ 'Employee'

        // สร้างชื่อเต็ม (Full Name)
        $full_name = $firstname . ' ' . $lastname;

        // สร้างคำสั่ง SQL เพื่อแทรกข้อมูลลงในตาราง employees
        $sql_employees = "INSERT INTO employees (EmployeeID, Position, Department, StartOfWork, Email, Tel, Name, profile_picture, role)
                          VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)";

        // เตรียมคำสั่ง SQL สำหรับ employees
        $stmt_employees = $conn->prepare($sql_employees);
        if (!$stmt_employees) {
            die("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับ employees: " . $conn->error);
        }

        // ผูกค่าตัวแปรกับคำสั่ง SQL
        $stmt_employees->bind_param("isssssss",
            $last_inserted_id, // EmployeeID
            $role, // Position
            $department, // Department
            $email, // Email
            $tel, // Tel
            $full_name, // Name
            $profile_picture, // profile_picture
            $role // role
        );

        if ($stmt_employees->execute()) {
            echo "ข้อมูลได้ถูกย้ายไปยังตาราง employees เรียบร้อยแล้ว!";
        } else {
            echo "เกิดข้อผิดพลาดในการย้ายข้อมูล: " . $stmt_employees->error;
        }

        // ปิด statement ของ employees หลังจากการใช้งานเสร็จ
        $stmt_employees->close();
    }
} else {
    echo "เกิดข้อผิดพลาดในการบันทึกข้อมูลในตาราง users: " . $stmt_users->error;
}

// ปิด statement ของ users หลังจากการใช้งานเสร็จ
$stmt_users->close();
$conn->close();
?>
