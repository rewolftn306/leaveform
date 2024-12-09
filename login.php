<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom right, #FFFF33, #F5F5F5);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .header {
            width: 100%;
            background-color: #000;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 0;
        }

        .header img {
            width: 100%;
            height: auto;
        }

        .login-container {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 8px;
            padding: 40px;
            width: 300px;
            text-align: center;
            margin-top: 150px;
        }

        h1 {
            font-size: 24px;
            color: white;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            font-size: 14px;
            color: white;
        }

        .input-group input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            outline: none;
        }

        .login-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        .login-btn:hover {
            background-color: #0056b3;
        }

        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 10px;
        }

        /* สไตล์สำหรับปุ่มสำหรับผู้พัฒนา */
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
    </style>
</head>
<body>
    <div class="header">
        <img src="Screenshot.png" alt="Logo">
    </div>

    <div class="login-container">
        <form action="process_login.php" method="POST">
            <h1>เข้าสู่ระบบ</h1>
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Username" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="login-btn">เข้าสู่ระบบ</button>
            <?php if (isset($_GET['error'])): ?>
                <p class="error-message"><?= htmlspecialchars($_GET['error']); ?></p>
            <?php endif; ?>
        </form>
    </div>

    <!-- ปุ่มสำหรับผู้พัฒนา -->
    <a href="register.php" class="developer-btn">ไปที่หน้าลงทะเบียน</a>

</body>
</html>
