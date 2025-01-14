<?php
// create_user.php
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

// สร้าง CSRF Token หากยังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    // รับและกรองข้อมูลจากฟอร์ม
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $position = isset($_POST['position']) ? trim($_POST['position']) : 'Unknown';
    $department = isset($_POST['department']) ? trim($_POST['department']) : 'Unknown';
    $tel = isset($_POST['tel']) ? trim($_POST['tel']) : 'Unknown';

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($username) || empty($password) || empty($firstname) || empty($lastname) || empty($role)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        // ตรวจสอบว่าชื่อผู้ใช้มีอยู่แล้วหรือไม่
        $stmt = $conn->prepare("SELECT UserID FROM users WHERE Username = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $error = "เกิดข้อผิดพลาดในการสร้างบัญชีผู้ใช้งาน";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "ชื่อผู้ใช้นี้ถูกใช้งานแล้ว";
            } else {
                $stmt->close();

                // จำกัดบทบาทที่ Admin สามารถสร้างได้
                $allowed_roles = ['Director', 'Admin', 'Employee'];
                if (!in_array($role, $allowed_roles)) {
                    $error = "ไม่สามารถสร้างบัญชีผู้ใช้งานด้วยบทบาทนี้ได้";
                } else {
                    // แฮชรหัสผ่าน
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // จัดการการอัปโหลดไฟล์โปรไฟล์ (ถ้ามี)
                    $profile_picture = NULL;
                    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);

                        if (!in_array($file_type, $allowed_types)) {
                            $error = "รูปภาพต้องเป็นไฟล์ประเภท JPG, PNG, หรือ GIF";
                        }

                        // จำกัดขนาดไฟล์ไม่เกิน 2MB
                        if ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) {
                            $error = "ขนาดไฟล์รูปภาพต้องไม่เกิน 2MB";
                        }

                        if (!isset($error)) {
                            $target_dir = "uploads/";
                            // สร้างชื่อไฟล์แบบไม่ซ้ำกัน
                            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                            $unique_name = uniqid('profile_', true) . '.' . $file_extension;
                            $target_file = $target_dir . $unique_name;

                            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                                $error = "ไม่สามารถอัปโหลดรูปภาพได้";
                            } else {
                                $profile_picture = $target_file;
                            }
                        }
                    }

                    if (!isset($error)) {
                        // เริ่มต้น Transaction เพื่อความปลอดภัย
                        $conn->begin_transaction();

                        try {
                            // แทรกข้อมูลผู้ใช้ใหม่
                            $sql = "INSERT INTO users (Username, Password, FirstName, LastName, Email, Role, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt_insert = $conn->prepare($sql);
                            if (!$stmt_insert) {
                                throw new Exception("Prepare failed: " . $conn->error);
                            }
                            $stmt_insert->bind_param("sssssss", $username, $hashed_password, $firstname, $lastname, $email, $role, $profile_picture);

                            if (!$stmt_insert->execute()) {
                                throw new Exception("Execute failed: " . $stmt_insert->error);
                            }

                            $last_inserted_id = $stmt_insert->insert_id;
                            $stmt_insert->close();

                            // หากบทบาทเป็น Director หรือ Admin ให้เพิ่มลงในตาราง approvers
                            $approverID_map = [
                                'Director' => 1,
                                'Admin' => 2
                            ];

                            if (array_key_exists($role, $approverID_map)) {
                                $approverID = $approverID_map[$role];
                                $stmt_approver = $conn->prepare("INSERT INTO approvers (UserID, ApproverID) VALUES (?, ?)");
                                if (!$stmt_approver) {
                                    throw new Exception("Prepare failed: " . $conn->error);
                                }
                                $stmt_approver->bind_param("ii", $last_inserted_id, $approverID);
                                if (!$stmt_approver->execute()) {
                                    throw new Exception("Execute failed: " . $stmt_approver->error);
                                }
                                $stmt_approver->close();
                            }

                            // หากเป็น Employee ให้แทรกลงในตาราง employees
                            if ($role === 'Employee') {
                                $sql_employees = "INSERT INTO employees (EmployeeID, Position, Department, StartOfWork, Email, Tel, Name, profile_picture, role) 
                                                  VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
                                $stmt_employees = $conn->prepare($sql_employees);
                                if (!$stmt_employees) {
                                    throw new Exception("Prepare failed: " . $conn->error);
                                }

                                $full_name = $firstname . ' ' . $lastname;
                                $role_employee = 'Employee';

                                $stmt_employees->bind_param("issssss", $last_inserted_id, $position, $department, $email, $tel, $full_name, $profile_picture, $role_employee);

                                if (!$stmt_employees->execute()) {
                                    throw new Exception("Execute failed: " . $stmt_employees->error);
                                }

                                $stmt_employees->close();
                            }

                            // Commit Transaction
                            $conn->commit();

                            $success = "สร้างบัญชีผู้ใช้งานสำเร็จ";
                        } catch (Exception $e) {
                            // Rollback Transaction ในกรณีที่เกิดข้อผิดพลาด
                            $conn->rollback();
                            error_log("Transaction failed: " . $e->getMessage());
                            $error = "เกิดข้อผิดพลาดในการสร้างบัญชีผู้ใช้งาน";
                        }
                    }
                }
            }
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สร้างบัญชีผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f9;
            padding-top: 70px;
        }
        .form-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: auto;
        }
        .btn-back {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
            display: inline-block;
        }
        .btn-back:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <span class="navbar-brand">ระบบการลาของบุคลากร มหาวิทยาลัยมหาสารคาม</span>
            <div class="d-flex">
                <span class="navbar-text me-3">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['Username']); ?></span>
                <form method="POST" action="logout.php" class="d-inline">
                    <button type="submit" name="logout" class="btn btn-outline-light">ออกจากระบบ</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <div class="form-container">
            <h2 class="text-center mb-4">สร้างบัญชีผู้ใช้งานใหม่</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label for="firstname" class="form-label">ชื่อ</label>
                    <input type="text" id="firstname" name="firstname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="lastname" class="form-label">นามสกุล</label>
                    <input type="text" id="lastname" name="lastname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">อีเมล์</label>
                    <input type="email" id="email" name="email" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">บทบาท</label>
                    <select id="role" name="role" class="form-select" required onchange="toggleEmployeeFields(this.value)">
                        <option value="">-- เลือกบทบาท --</option>
                        <option value="Director">อธิบดี (Director)</option>
                        <option value="Admin">ผู้ดูแลระบบ (Admin)</option>
                        <option value="Employee">พนักงาน (Employee)</option>
                    </select>
                </div>
                <!-- ฟิลด์เพิ่มเติมสำหรับ Employee -->
                <div id="employee_fields" style="display: none;">
                    <div class="mb-3">
                        <label for="position" class="form-label">ตำแหน่ง</label>
                        <input type="text" id="position" name="position" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="department" class="form-label">แผนก</label>
                        <input type="text" id="department" name="department" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="tel" class="form-label">โทรศัพท์</label>
                        <input type="text" id="tel" name="tel" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="profile_picture" class="form-label">โปรไฟล์รูปภาพ</label>
                    <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/*" required>
                </div>
                <!-- ใส่ CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" name="create_user" class="btn btn-primary w-100">สร้างบัญชีผู้ใช้งาน</button>
            </form>
            <a href="manage_approvers.php" class="btn-back">จัดการผู้อนุมัติ</a>
        </div>
    </div>

    <!-- Bootstrap JS และ JavaScript สำหรับแสดงฟิลด์เพิ่มเติม -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleEmployeeFields(role) {
            const employeeFields = document.getElementById('employee_fields');
            if (role === 'Employee') {
                employeeFields.style.display = 'block';
            } else {
                employeeFields.style.display = 'none';
                // ล้างค่าฟิลด์เมื่อไม่เลือกเป็น Employee
                document.getElementById('position').value = '';
                document.getElementById('department').value = '';
                document.getElementById('tel').value = '';
            }
        }
    </script>
</body>
</html>
