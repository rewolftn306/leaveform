<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนผู้ใช้</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f4f4f9;
        }
        form {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 16px;
            border-radius: 4px;
        }
        button:hover {
            background-color: #45a049;
        }
        .btn-back {
            background-color: #f44336;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 10px;
        }
        .btn-back:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>

<h2 style="text-align:center;">ฟอร์มการลงทะเบียนผู้ใช้</h2>

<form action="register_process.php" method="POST" enctype="multipart/form-data">
        <label for="username">ชื่อผู้ใช้:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="password">รหัสผ่าน:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        
        <label for="firstname">ชื่อ:</label><br>
        <input type="text" id="firstname" name="firstname" required><br><br>
        
        <label for="lastname">นามสกุล:</label><br>
        <input type="text" id="lastname" name="lastname" required><br><br>
        
        <label for="email">อีเมล์:</label><br>
        <input type="email" id="email" name="email"><br><br>
        
        <label for="role">ตำแหน่ง:</label><br>
        <select id="role" name="role" required>
            <option value="">-- เลือกตำแหน่ง --</option>
            <option value="Employee">พนักงาน (Employee)</option>
            <option value="Director">อธิบดี (Director)</option>
            <option value="Admin">ผู้ดูแล (Admin)</option>
            <!-- คุณสามารถเพิ่มตัวเลือกตำแหน่งอื่นๆ ได้ที่นี่ -->
        </select><br><br>

        <label for="position">ตำแหน่งในบริษัท:</label><br>
        <input type="text" id="position" name="position"><br><br>

        <label for="department">แผนก:</label><br>
        <input type="text" id="department" name="department"><br><br>

        <label for="tel">เบอร์โทรศัพท์:</label><br>
        <input type="text" id="tel" name="tel"><br><br>

    <!-- ช่องสำหรับอัปโหลดโปรไฟล์ -->
    <label for="profile_picture">โปรไฟล์:</label>
    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" required><br><br>

    <button type="submit">ลงทะเบียน</button>
</form>

<!-- ปุ่มย้อนกลับ -->
<a href="login.php" class="btn-back">ย้อนกลับไปที่หน้าเข้าสู่ระบบ</a>

</body>
</html>
