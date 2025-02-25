<?php
// register_process.php
session_start();

// ตรวจสอบว่า CSRF token ถูกส่งมาหรือไม่
if (!isset($_POST['csrf_token'])) {
    die("Invalid CSRF token: Token not set");
}

// ตรวจสอบว่า CSRF token ตรงกันหรือไม่ โดยใช้ hash_equals เพื่อความปลอดภัย
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token: Token mismatch");
}

include('connect.php');

// ฟังก์ชั่นสำหรับการกรองข้อมูล
function sanitize_input($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}

// รับและกรองข้อมูลจากฟอร์ม
$username = sanitize_input($_POST['username']);
$password = $_POST['password']; // รหัสผ่านจะถูกแฮช
$firstname = sanitize_input($_POST['firstname']);
$lastname = sanitize_input($_POST['lastname']);
$email = isset($_POST['email']) ? (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? sanitize_input($_POST['email']) : NULL) : NULL;
$role = sanitize_input($_POST['role']);

// รับข้อมูลเพิ่มเติมสำหรับ Employee (ถ้ามี)
$position = isset($_POST['position']) ? sanitize_input($_POST['position']) : '';
$department = isset($_POST['department']) ? sanitize_input($_POST['department']) : '';
$tel = isset($_POST['tel']) ? sanitize_input($_POST['tel']) : '';

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($username) || empty($password) || empty($firstname) || empty($lastname) || empty($role)) {
    header("Location: register.php?error=กรุณากรอกข้อมูลให้ครบถ้วน");
    exit();
}

// จำกัดการสมัครบทบาท
$allowed_roles = ['Employee']; // กำหนดบทบาทที่อนุญาตให้สมัครได้
if (!in_array($role, $allowed_roles)) {
    header("Location: register.php?error=ไม่สามารถสมัครเป็นบทบาทนี้ได้");
    exit();
}

// ตรวจสอบว่าชื่อผู้ใช้มีอยู่แล้วหรือไม่
$stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    header("Location: register.php?error=เกิดข้อผิดพลาดในการลงทะเบียน");
    exit();
}
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    header("Location: register.php?error=ชื่อผู้ใช้นี้ถูกใช้งานแล้ว");
    exit();
}
$stmt->close();

// จัดการการอัปโหลดไฟล์โปรไฟล์
$profile_picture = NULL;
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        header("Location: register.php?error=รูปภาพต้องเป็นไฟล์ประเภท JPG, PNG, หรือ GIF");
        exit();
    }

    // จำกัดขนาดไฟล์ไม่เกิน 2MB
    if ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) {
        header("Location: register.php?error=ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB");
        exit();
    }

    // อ่านเนื้อหาของไฟล์แล้วแปลงเป็น base64
    $image_data = file_get_contents($_FILES['profile_picture']['tmp_name']);
    $base64_image = base64_encode($image_data);

    // เก็บรูปแบบ base64 พร้อม MIME type
    $profile_picture = "data:$file_type;base64," . $base64_image;

    // 📌 ณ จุดนี้ สามารถนำ `$profile_picture` ไปบันทึกลงฐานข้อมูลได้
} else {
    header("Location: register.php?error=กรุณาอัปโหลดรูปภาพโปรไฟล์");
    exit();
}


// แฮชรหัสผ่าน
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// เริ่มต้น Transaction เพื่อความปลอดภัย
$conn->begin_transaction();

try {
    // แทรกข้อมูลลงในตาราง users
    $sql_users = "INSERT INTO users (Username, Password, FirstName, LastName, Email, Role, profile_picture) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_users = $conn->prepare($sql_users);
    if (!$stmt_users) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt_users->bind_param("sssssss", $username, $hashed_password, $firstname, $lastname, $email, $role, $profile_picture);

    if (!$stmt_users->execute()) {
        throw new Exception("Execute failed: " . $stmt_users->error);
    }

    $last_inserted_id = $stmt_users->insert_id;
    $stmt_users->close();

    // ถ้า Role เป็น Employee ให้แทรกข้อมูลลงในตาราง employees
    if ($role === "Employee") {
        $sql_employees = "INSERT INTO employees (EmployeeID, Position, Department, StartOfWork, Email, Tel, Name, profile_picture, role) 
                          VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
        $stmt_employees = $conn->prepare($sql_employees);
        if (!$stmt_employees) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $full_name = $firstname . ' ' . $lastname;
        $role_employee = 'Employee';

        $stmt_employees->bind_param("isssssss", $last_inserted_id, $position, $department, $email, $tel, $full_name, $profile_picture, $role_employee);

        if (!$stmt_employees->execute()) {
            throw new Exception("Execute failed: " . $stmt_employees->error);
        }

        $stmt_employees->close();
    }

    // Commit Transaction
    $conn->commit();

    // รีไดเรกต์ไปยังหน้า login พร้อมข้อความสำเร็จ
    header("Location: login.php?success=ลงทะเบียนสำเร็จ!");
    exit();
} catch (Exception $e) {
    // Rollback Transaction ในกรณีที่เกิดข้อผิดพลาด
    $conn->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    header("Location: register.php?error=เกิดข้อผิดพลาดในการลงทะเบียน");
    exit();
}

$conn->close();
?>