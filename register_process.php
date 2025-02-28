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

} else {
    header("Location: register.php?error=กรุณาอัปโหลดรูปภาพโปรไฟล์");
    exit();
}

// ลายเซ็น (รับจาก canvas หรืออัปโหลดไฟล์)
$signature_data = isset($_POST['signature_data']) ? $_POST['signature_data'] : null;
$signature_image = NULL;

// หากมีลายเซ็นจากการเขียนด้วยเมาส์
if ($signature_data) {
    $signature_image = $signature_data;
}

// หากมีการอัปโหลดไฟล์ลายเซ็น
if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($_FILES['signature_image']['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        header("Location: register.php?error=รูปภาพลายเซ็นต้องเป็นไฟล์ประเภท JPG, PNG, หรือ GIF");
        exit();
    }

    // จำกัดขนาดไฟล์ไม่เกิน 2MB
    if ($_FILES['signature_image']['size'] > 2 * 1024 * 1024) {
        header("Location: register.php?error=ขนาดไฟล์รูปภาพลายเซ็นต้องไม่เกิน 2MB");
        exit();
    }

    // อ่านเนื้อหาของไฟล์แล้วแปลงเป็น base64
    $image_data = file_get_contents($_FILES['signature_image']['tmp_name']);
    $base64_image = base64_encode($image_data);

    // เก็บรูปแบบ base64 พร้อม MIME type
    $signature_image = "data:$file_type;base64," . $base64_image;
}

// หากไม่มีข้อมูลลายเซ็นทั้งจากการเขียนหรืออัปโหลด ให้แสดงข้อผิดพลาด
if (!$signature_image) {
    header("Location: register.php?error=กรุณากรอกหรืออัปโหลดลายเซ็น");
    exit();
}

// แฮชรหัสผ่าน
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// เริ่มต้น Transaction เพื่อความปลอดภัย
$conn->begin_transaction();

try {
    // แทรกข้อมูลลงในตาราง users
    $sql_users = "INSERT INTO users (Username, Password, FirstName, LastName, Email, Role, profile_picture, Position, Department, Tel, Signature) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_users = $conn->prepare($sql_users);
    if (!$stmt_users) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt_users->bind_param("sssssssssss", $username, $hashed_password, $firstname, $lastname, $email, $role, $profile_picture, $position, $department, $tel, $signature_image);

    if (!$stmt_users->execute()) {
        throw new Exception("Execute failed: " . $stmt_users->error);
    }

    $last_inserted_id = $stmt_users->insert_id;
    $stmt_users->close();

    // Commit Transaction
    $conn->commit();

    // หลังจากการลงทะเบียนสำเร็จ
// เช็คบทบาทของผู้ใช้ที่ลงทะเบียน
    if ($role === 'Admin') {
        // หากผู้ใช้เป็น Admin, ให้ไปที่หน้า edit_user.php
        header("Location: manage_users.php?success=ลงทะเบียนสำเร็จ!");
        exit();
    } else {
        // ถ้าไม่ใช่ Admin, ให้ไปที่หน้า login พร้อมข้อความสำเร็จ
        header("Location: login.php?success=ลงทะเบียนสำเร็จ!");
        exit();
    }
} catch (Exception $e) {
    // Rollback Transaction ในกรณีที่เกิดข้อผิดพลาด
    $conn->rollback();
    error_log("Transaction failed: " . $e->getMessage());
    header("Location: register.php?error=เกิดข้อผิดพลาดในการลงทะเบียน");
    exit();
}

$conn->close();
?>