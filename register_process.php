<?php
// register_process.php
session_start();

// รวมไฟล์เชื่อมต่อฐานข้อมูล
include('connect.php'); // ตรวจสอบว่า path ถูกต้อง

// ฟังก์ชั่นสำหรับการกรองข้อมูล
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF Token ถ้ามีการใช้งาน
    /*
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
    */

    // รับและกรองข้อมูลจากฟอร์ม
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // รหัสผ่านจะถูกแฮช
    $firstname = sanitize_input($_POST['firstname']);
    $lastname = sanitize_input($_POST['lastname']);
    $email = isset($_POST['email']) ? (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? sanitize_input($_POST['email']) : NULL) : NULL;
    $role = sanitize_input($_POST['role']);
    $position = isset($_POST['position']) ? sanitize_input($_POST['position']) : NULL;
    $department = isset($_POST['department']) ? sanitize_input($_POST['department']) : NULL;
    $tel = isset($_POST['tel']) ? sanitize_input($_POST['tel']) : NULL;

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($username) || empty($password) || empty($firstname) || empty($lastname) || empty($role)) {
        header("Location: register.php?error=กรุณากรอกข้อมูลให้ครบถ้วน");
        exit;
    }

    // ตรวจสอบว่าชื่อผู้ใช้มีอยู่แล้วหรือไม่
    $stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        header("Location: register.php?error=เกิดข้อผิดพลาดในการลงทะเบียน");
        exit;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        header("Location: register.php?error=ชื่อผู้ใช้นี้ถูกใช้งานแล้ว");
        exit;
    }
    $stmt->close();

    // จัดการการอัปโหลดไฟล์โปรไฟล์
    $profile_picture = NULL;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);

        if (!in_array($file_type, $allowed_types)) {
            header("Location: register.php?error=รูปภาพต้องเป็นไฟล์ประเภท JPG, PNG, หรือ GIF");
            exit;
        }

        // จำกัดขนาดไฟล์ไม่เกิน 2MB
        if ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) {
            header("Location: register.php?error=ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB");
            exit;
        }

        $target_dir = "uploads/";
        // สร้างชื่อไฟล์แบบไม่ซ้ำกัน
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $unique_name = uniqid('profile_', true) . '.' . $file_extension;
        $target_file = $target_dir . $unique_name;

        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            header("Location: register.php?error=ไม่สามารถอัปโหลดรูปภาพได้");
            exit;
        }

        $profile_picture = $target_file;
    } else {
        header("Location: register.php?error=กรุณาอัปโหลดรูปภาพโปรไฟล์");
        exit;
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
            $stmt_employees->bind_param("isssssss",
                $last_inserted_id, // EmployeeID (i)
                $position,         // Position (s)
                $department,       // Department (s)
                $email,            // Email (s)
                $tel,              // Tel (s)
                $full_name,        // Name (s)
                $profile_picture,  // profile_picture (s)
                $role              // role (s)
            );

            if (!$stmt_employees->execute()) {
                throw new Exception("Execute failed: " . $stmt_employees->error);
            }

            $stmt_employees->close();
        }

        // Commit Transaction
        $conn->commit();

        // รีไดเรกต์ไปยังหน้า login พร้อมข้อความสำเร็จ
        if ($role === "Employee") {
            header("Location: login.php?success=ลงทะเบียนสำเร็จ! ข้อมูลได้ถูกย้ายไปยังตาราง employees เรียบร้อยแล้ว");
        } else {
            header("Location: login.php?success=ลงทะเบียนสำเร็จ!");
        }
        exit();
    } catch (Exception $e) {
        // Rollback Transaction ในกรณีที่เกิดข้อผิดพลาด
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        header("Location: register.php?error=เกิดข้อผิดพลาดในการลงทะเบียน");
        exit();
    }
}

$conn->close();
?>
