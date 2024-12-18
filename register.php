<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนผู้ใช้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f9;
            padding-top: 60px;
        }
        .register-container {
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
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">ฟอร์มการลงทะเบียนผู้ใช้</h2>
            <?php if (isset($_GET['error'])): ?>
                <p class="error-message"><?= htmlspecialchars($_GET['error']); ?></p>
            <?php endif; ?>
            <form action="register_process.php" method="POST" enctype="multipart/form-data" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">ชื่อผู้ใช้:</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">รหัสผ่าน:</label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="mb-3">
                    <label for="firstname" class="form-label">ชื่อ:</label>
                    <input type="text" id="firstname" name="firstname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="lastname" class="form-label">นามสกุล:</label>
                    <input type="text" id="lastname" name="lastname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">อีเมล์:</label>
                    <input type="email" id="email" name="email" class="form-control">
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">ตำแหน่ง:</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="">-- เลือกตำแหน่ง --</option>
                        <option value="Employee">พนักงาน (Employee)</option>
                        <option value="Director">อธิบดี (Director)</option>
                        <option value="Admin">ผู้ดูแล (Admin)</option>
                        <!-- คุณสามารถเพิ่มตัวเลือกตำแหน่งอื่นๆ ได้ที่นี่ -->
                    </select>
                </div>
                <!-- ฟิลด์เพิ่มเติมสำหรับพนักงาน -->
                <div id="employee-fields" style="display: none;">
                    <div class="mb-3">
                        <label for="position" class="form-label">ตำแหน่งในบริษัท:</label>
                        <input type="text" id="position" name="position" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="department" class="form-label">แผนก:</label>
                        <input type="text" id="department" name="department" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="tel" class="form-label">เบอร์โทรศัพท์:</label>
                        <input type="text" id="tel" name="tel" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="profile_picture" class="form-label">โปรไฟล์:</label>
                    <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept="image/*" required>
                </div>
                <button type="submit" class="btn btn-success w-100">ลงทะเบียน</button>
            </form>
            <a href="login.php" class="btn-back">ย้อนกลับไปที่หน้าเข้าสู่ระบบ</a>
        </div>
    </div>

    <!-- Bootstrap JS และ JavaScript สำหรับแสดงฟิลด์เพิ่มเติม -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // แสดง/ซ่อนฟิลด์เพิ่มเติมเมื่อเลือกตำแหน่ง
        document.getElementById('role').addEventListener('change', function() {
            const employeeFields = document.getElementById('employee-fields');
            if (this.value === 'Employee') {
                employeeFields.style.display = 'block';
            } else {
                employeeFields.style.display = 'none';
            }
        });

        // แสดงป๊อปอัพแจ้งเตือนข้อผิดพลาด
        <?php if (isset($_GET['error'])): ?>
            alert("<?= addslashes($_GET['error']); ?>");
        <?php endif; ?>
    </script>
</body>
</html>
