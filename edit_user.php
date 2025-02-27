<?php
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

// ตรวจสอบว่ามีการส่ง parameter `userid` มาหรือไม่
if (!isset($_GET['userid'])) {
    header("Location: manage_users.php?error=ไม่พบผู้ใช้งาน");
    exit;
}

$userID = intval($_GET['userid']);

// ดึงข้อมูลผู้ใช้งานที่ต้องการแก้ไข
$stmt = $conn->prepare("SELECT Username, FirstName, LastName, Email, Role, profile_picture FROM users WHERE UserID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage_users.php?error=ไม่พบผู้ใช้งาน");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// สร้าง CSRF Token หากยังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    // รับและกรองข้อมูลจากฟอร์ม
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($email) || empty($role) || empty($firstname) || empty($lastname)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        // จำกัดบทบาทที่ Admin สามารถตั้งได้
        $allowed_roles = ['Director', 'Leader', 'Admin', 'Employee'];
        if (!in_array($role, $allowed_roles)) {
            $error = "ไม่สามารถตั้งบทบาทนี้ได้";
        } else {
            // จัดการการอัปโหลดไฟล์โปรไฟล์ (ถ้ามี)
            $profile_picture = $user['profile_picture'];
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
                        // ลบไฟล์เก่า
                        if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                            unlink($user['profile_picture']);
                        }
                    }
                }
            }

            if (!isset($error)) {
                // แฮชรหัสผ่านถ้ามีการเปลี่ยนแปลง
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET Password = ?, Email = ?, FirstName = ?, LastName = ?, Role = ?, profile_picture = ? WHERE UserID = ?";
                    $stmt_update = $conn->prepare($sql);
                    $stmt_update->bind_param("ssssssi", $hashed_password, $email, $firstname, $lastname, $role, $profile_picture, $userID);
                } else {
                    $sql = "UPDATE users SET Email = ?, FirstName = ?, LastName = ?, Role = ?, profile_picture = ? WHERE UserID = ?";
                    $stmt_update = $conn->prepare($sql);
                    $stmt_update->bind_param("sssssi", $email, $firstname, $lastname, $role, $profile_picture, $userID);
                }

                // ตรวจสอบว่าการอัปเดตข้อมูลสำเร็จหรือไม่
                if ($stmt_update->execute()) {
                    header("Location: manage_users.php?success=แก้ไขข้อมูลผู้ใช้งานสำเร็จ");
                    exit();
                } else {
                    $error = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลผู้ใช้งาน: " . htmlspecialchars($stmt_update->error);
                }

                $stmt_update->close();
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
    <title>แก้ไขข้อมูลผู้ใช้งาน</title>
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

        .current-profile {
            margin-top: 10px;
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
            <h2 class="text-center mb-4">แก้ไขข้อมูลผู้ใช้งาน</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">ชื่อผู้ใช้:</label>
                    <input type="text" id="username" name="username" class="form-control"
                        value="<?= htmlspecialchars($user['Username']); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">รหัสผ่าน (เปลี่ยนรหัสผ่านใหม่):</label>
                    <input type="password" id="password" name="password" class="form-control" minlength="6">
                    <small class="text-muted">ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน ให้เว้นว่างไว้</small>
                </div>
                <div class="mb-3">
                    <label for="firstname" class="form-label">ชื่อ:</label>
                    <input type="text" id="firstname" name="firstname" class="form-control"
                        value="<?= htmlspecialchars($user['FirstName']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="lastname" class="form-label">นามสกุล:</label>
                    <input type="text" id="lastname" name="lastname" class="form-control"
                        value="<?= htmlspecialchars($user['LastName']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">อีเมล์:</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="<?= htmlspecialchars($user['Email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">ตำแหน่ง:</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="">-- เลือกตำแหน่ง --</option>
                        <option value="Director" <?= ($user['Role'] === 'Director') ? 'selected' : ''; ?>>อธิบดี
                            (Director)</option>
                        <option value="Leader" <?= ($user['Role'] === 'Leader') ? 'selected' : ''; ?>>หัวหน้า
                            (Leader)</option>
                        <option value="Admin" <?= ($user['Role'] === 'Admin') ? 'selected' : ''; ?>>ผู้ดูแลระบบ (Admin)
                        </option>
                        <option value="Employee" <?= ($user['Role'] === 'Employee') ? 'selected' : ''; ?>>พนักงาน
                            (Employee)
                        </option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="profile_picture" class="form-label">โปรไฟล์:</label>
                    <input type="file" id="profile_picture" name="profile_picture" class="form-control"
                        accept="image/*">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <div class="current-profile">
                            <img src="<?= htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture"
                                class="img-thumbnail mt-2" width="150">
                        </div>
                    <?php endif; ?>
                </div>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <button type="submit" name="update_user" class="btn btn-primary w-100">อัปเดตข้อมูล</button>
            </form>
            <a href="manage_users.php" class="btn-back">ย้อนกลับ</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>