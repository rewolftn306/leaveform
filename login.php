<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom right, #FFFFFF, #6699FF);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-container {
            background-color: rgba(0, 0, 0, 0.8);
            border-radius: 8px;
            padding: 40px;
            width: 350px;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .login-container h1 {
            margin-bottom: 20px;
            text-align: center;
        }
        .login-btn {
            width: 100%;
            margin-top: 10px;
        }
        .error-message {
            color: red;
            margin-top: 10px;
            text-align: center;
        }
        .developer-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #ff5722;
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .developer-btn:hover {
            background-color: #e64a19;
        }
        .header img {
            width: 100%;
            height: auto;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="ITMSU.png" alt="Logo">
        <h1>เข้าสู่ระบบ</h1>
        <form action="process_login.php" method="POST" novalidate>
            <div class="mb-3">
                <label for="username" class="form-label">ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="ชื่อผู้ใช้" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="รหัสผ่าน" required>
            </div>
            <button type="submit" class="btn btn-primary login-btn">เข้าสู่ระบบ</button>
            <?php if (isset($_GET['error'])): ?>
                <p class="error-message"><?= htmlspecialchars($_GET['error']); ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['message'])): ?>
                <p class="text-success text-center"><?= htmlspecialchars($_GET['message']); ?></p>
            <?php endif; ?>
        </form>
    </div>

    <!-- ปุ่มสำหรับผู้พัฒนา -->
    <a href="register.php" class="developer-btn">ไปที่หน้าลงทะเบียน</a>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript สำหรับแสดงป๊อปอัพแจ้งเตือน -->
    <script>
        <?php if (isset($_GET['success'])): ?>
            alert("<?= addslashes($_GET['success']); ?>");
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            alert("<?= addslashes($_GET['error']); ?>");
        <?php endif; ?>
    </script>
</body>
</html>
