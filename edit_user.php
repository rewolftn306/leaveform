<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

include('connect.php');

// ตรวจสอบว่ามีการส่ง parameter `userid` มาหรือไม่
if (!isset($_GET['userid'])) {
    header("Location: manage_users.php?error=ไม่พบผู้ใช้งาน");
    exit;
}


$userID = intval($_GET['userid']);

// ตรวจสอบว่าผู้ใช้เป็นคนที่ต้องการแก้ไขข้อมูลหรือไม่
if ($_SESSION['UserID'] !== $userID && $_SESSION['Role'] !== 'Admin') {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// ดึงข้อมูลผู้ใช้งานที่ต้องการแก้ไข
$stmt = $conn->prepare("SELECT Username, FirstName, LastName, Email, Role, profile_picture, Signature FROM users WHERE UserID = ?");
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
    // ตรวจสอบค่าของ 'role' ก่อนที่จะใช้งาน
    $role = isset($_POST['role']) ? trim($_POST['role']) : $user['Role'];  // กำหนดค่า default เป็นค่าจากฐานข้อมูลถ้าไม่มีการส่งมา
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);

    // ตรวจสอบข้อมูลที่จำเป็น
    if (empty($email) || empty($role) || empty($firstname) || empty($lastname)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        // จำกัดบทบาทที่ Admin สามารถตั้งได้
        $allowed_roles = ['Director', 'Leader', 'Leader2', 'Leader3', 'Admin', 'Employee'];
        if ($_SESSION['Role'] !== 'Admin' && !in_array($role, ['Employee', 'Leader', 'Leader2', 'Leader3', 'Director'])) {
            $error = "ไม่สามารถตั้งบทบาทนี้ได้";
        } else {
            // จัดการการอัปโหลดไฟล์โปรไฟล์
            $profile_picture = $profile_picture; // ใช้โปรไฟล์เดิมหากไม่มีการอัปโหลดใหม่
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
                    // อ่านเนื้อหาของไฟล์แล้วแปลงเป็น base64
                    $image_data = file_get_contents($_FILES['profile_picture']['tmp_name']);
                    $base64_image = base64_encode($image_data);

                    // เก็บรูปแบบ base64 พร้อม MIME type
                    $profile_picture = "data:$file_type;base64," . $base64_image;
                }
            }

            // รับลายเซ็นจากการเขียนหรืออัปโหลด
            $signature_data = isset($_POST['signature_data']) ? $_POST['signature_data'] : null;
            $signature_image = NULL;

            if ($signature_data) {
                $signature_image = $signature_data;
            }

            if (isset($_FILES['signature_image']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($_FILES['signature_image']['tmp_name']);

                if (!in_array($file_type, $allowed_types)) {
                    $error = "รูปภาพลายเซ็นต้องเป็นไฟล์ประเภท JPG, PNG, หรือ GIF";
                }

                if ($_FILES['signature_image']['size'] > 2 * 1024 * 1024) {
                    $error = "ขนาดไฟล์รูปภาพลายเซ็นต้องไม่เกิน 2MB";
                }

                if (!isset($error)) {
                    $image_data = file_get_contents($_FILES['signature_image']['tmp_name']);
                    $base64_image = base64_encode($image_data);
                    $signature_image = "data:$file_type;base64," . $base64_image;
                }
            }

            if (!$signature_image) {
                $error = "กรุณากรอกหรืออัปโหลดลายเซ็น";
            }

            if (!isset($error)) {
                // แฮชรหัสผ่านถ้ามีการเปลี่ยนแปลง
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET Password = ?, Email = ?, FirstName = ?, LastName = ?, Role = ?, profile_picture = ?, Signature = ? WHERE UserID = ?";
                    $stmt_update = $conn->prepare($sql);
                    $stmt_update->bind_param("sssssssi", $hashed_password, $email, $firstname, $lastname, $role, $profile_picture, $signature_image, $userID);
                } else {
                    $sql = "UPDATE users SET Email = ?, FirstName = ?, LastName = ?, Role = ?, profile_picture = ?, Signature = ? WHERE UserID = ?";
                    $stmt_update = $conn->prepare($sql);
                    $stmt_update->bind_param("ssssssi", $email, $firstname, $lastname, $role, $profile_picture, $signature_image, $userID);
                }

                if ($stmt_update->execute()) {
                    // ตรวจสอบว่าผู้ใช้เป็น Admin หรือไม่
                    if ($_SESSION['Role'] === 'Admin') {
                        // หากเป็น Admin, redirect ไปยัง manage_users.php
                        header("Location: manage_users.php?success=แก้ไขข้อมูลผู้ใช้งานสำเร็จ");
                    } else {
                        // หากไม่ใช่ Admin, redirect ไปยัง index.php
                        header("Location: index.php");
                    }
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
                    <select id="role" name="role" class="form-select" required <?= ($_SESSION['Role'] !== 'Admin') ? 'disabled' : ''; ?>>
                        <option value="">-- เลือกตำแหน่ง --</option>
                        <option value="Director" <?= ($user['Role'] === 'Director') ? 'selected' : ''; ?>>อธิบดี (Director)
                        </option>
                        <option value="Leader" <?= ($user['Role'] === 'Leader') ? 'selected' : ''; ?>>หัวหน้า (Leader)
                        </option>
                        <option value="Leader2" <?= ($user['Role'] === 'Leader2') ? 'selected' : ''; ?>>หัวหน้า2 (Leader2)
                        </option>
                        <option value="Leader3" <?= ($user['Role'] === 'Leader3') ? 'selected' : ''; ?>>หัวหน้า3 (Leader3)
                        </option>
                        <option value="Admin" <?= ($user['Role'] === 'Admin') ? 'selected' : ''; ?>>ผู้ดูแลระบบ (Admin)
                        </option>
                        <option value="Employee" <?= ($user['Role'] === 'Employee') ? 'selected' : ''; ?>>พนักงาน
                            (Employee)</option>
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
                <div class="mb-3">
                    <label for="signature" class="form-label">ลายเซ็น</label>
                    <div class="canvas-container">
                        <canvas id="signatureCanvas" width="400" height="200"
                            style="border: 2px dashed #ccc; border-radius: 10px;"></canvas>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <button type="button" class="btn btn-outline-secondary" id="clearCanvas">ล้างลายเซ็น</button>
                        <span class="text-muted" id="clearMessage" style="display: none;">ลายเซ็นถูกล้างแล้ว</span>
                    </div>
                    <input type="hidden" name="signature_data" id="signature_data">
                </div>
                <div class="mb-3">
                    <label for="signature_image" class="form-label">หรืออัปโหลดลายเซ็น</label>
                    <input type="file" class="form-control" name="signature_image" id="signature_image"
                        accept="image/*">
                </div>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <button type="submit" name="update_user" class="btn btn-primary w-100">อัปเดตข้อมูล</button>
            </form>
            <?php
            // ตรวจสอบบทบาทของผู้ใช้
            if ($_SESSION['Role'] === 'Admin') {
                $redirect_link = "manage_users.php";
            } else {
                $redirect_link = "index.php";
            }
            ?>
            <a href="<?= $redirect_link ?>" class="btn-back">ย้อนกลับ</a>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;

        // ฟังก์ชันในการเริ่มต้นการวาด
        canvas.addEventListener('mousedown', (e) => {
            isDrawing = true;
            ctx.beginPath();
            ctx.moveTo(e.offsetX, e.offsetY);
        });

        // ฟังก์ชันในการลาก
        canvas.addEventListener('mousemove', (e) => {
            if (isDrawing) {
                ctx.lineTo(e.offsetX, e.offsetY);
                ctx.stroke();
            }
        });

        // ฟังก์ชันในการหยุดการวาด
        canvas.addEventListener('mouseup', () => {
            isDrawing = false;
        });

        // ฟังก์ชันในการเคลียร์ canvas
        document.getElementById('clearCanvas').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById('signature_data').value = ''; // ลบข้อมูล base64
            document.getElementById('clearMessage').style.display = 'inline'; // แสดงข้อความล้างลายเซ็น
        });

        // เมื่อฟอร์มถูกส่ง จะทำการแปลง canvas เป็น base64
        document.querySelector('form').addEventListener('submit', () => {
            const signatureData = canvas.toDataURL();
            document.getElementById('signature_data').value = signatureData;
        });
    </script>

</body>

</html>