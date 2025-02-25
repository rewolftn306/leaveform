<?php
// register.php
session_start();

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
                        <option value="">-- เลือกบทบาท --</option>
                        <option value="Employee">พนักงาน (Employee)</option>
                        <!-- คุณสามารถเพิ่มบทบาทอื่นๆ ได้ที่นี่ -->
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

                <!-- ใส่ CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                <button type="submit" class="btn btn-primary w-100">ลงทะเบียน</button>
                <p><a href="login.php" class="btn btn-danger float-end mt-3 w-20">ย้อนกลับ</a></p>
            </form>
            <p class="mt-3 text-center">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>