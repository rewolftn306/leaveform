<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบบทบาทของผู้ใช้ (Admin เท่านั้นที่สามารถเข้าถึงได้)
if ($_SESSION['Role'] !== 'Admin') {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// สร้าง CSRF token หากยังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนผู้ใช้ใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f9;
            padding-top: 50px;
        }

        .register-container {
            max-width: 500px;
            margin: auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        .canvas-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            background-color: #f7f7f7;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
        }

        #signatureCanvas {
            width: 100%;
            height: 100%;
            border-radius: 8px;
        }

        .btn-outline-secondary {
            padding: 5px 15px;
        }

        .text-muted {
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">ลงทะเบียนผู้ใช้ใหม่</h2>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            <form action="register_process.php" method="POST" enctype="multipart/form-data" novalidate>
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
                    <select id="role" name="role" class="form-select" required>
                        <option value="">-- เลือกตำแหน่ง --</option>
                        <option value="Director">อธิบดี (Director)</option>
                        <option value="Leader">หัวหน้า (Leader)</option>
                        <option value="Admin">ผู้ดูแลระบบ (Admin)</option>
                        <option value="Employee">พนักงาน (Employee)</option>
                    </select>
                </div>

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

                <div class="mb-3">
                    <label for="profile_picture" class="form-label">โปรไฟล์รูปภาพ</label>
                    <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/*"
                        required>
                </div>

                <div class="mb-3">
                    <label for="signature" class="form-label">กรุณากรอกลายเซ็น</label>
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

                <!-- ใส่ CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" class="btn btn-primary w-100">ลงทะเบียน</button>
                <p><a href="edit_user.php" class="btn btn-danger float-end mt-3 w-20">ย้อนกลับ</a></p>
            </form>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
