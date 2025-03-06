<?php
session_start();
require_once 'connect.php'; // ใช้ require_once เพื่อป้องกันการเรียกซ้ำ

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!$conn) {
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
}

// ดึงข้อมูลผู้ใช้ทั้งหมดจากฐานข้อมูล
$sql = "SELECT UserID, FirstName, LastName, Position, Department, Tel, Email, profile_picture FROM users";
$result = $conn->query($sql);

// ตรวจสอบว่ามีข้อมูลหรือไม่
if ($result->num_rows == 0) {
    echo "<p>ไม่พบข้อมูลผู้ใช้</p>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบข้อมูลบุคลากร</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .navbar {
            background: #007BFF;
            padding: 10px;
            text-align: left;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 18px;
        }
        h2 {
            text-align: center;
        }
        .content {
            display: flex;
            transition: all 0.3s ease;
        }
        .profile-box {
            width: 300px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            display: none;
            position: relative;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: red;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 5px;
        }
        .profile-box img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: block;
            margin: auto;
        }
        .profile-box h3, .profile-box p {
            text-align: center;
        }
        .table-container {
            flex: 1;
            transition: margin-left 0.3s ease;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="index.php">← กลับหน้าหลัก</a>
    </div>
    <h2>ระบบข้อมูลบุคลากร</h2>
    <div class="content">
        <div class="profile-box" id="profileBox">
            <button class="close-btn" onclick="closeProfile()">×</button>
            <img id="profileImage" src="" alt="Profile Picture">
            <h3 id="profileName"></h3>
            <p id="profilePosition"></p>
            <p id="profileDepartment"></p>
        </div>
        <div class="table-container" id="tableContainer">
            <table>
                <tr>
                    <th>ชื่อ</th>
                    <th>นามสกุล</th>
                    <th>ตำแหน่ง</th>
                    <th>แผนก</th>
                    <th>เบอร์โทร</th>
                    <th>อีเมล</th>
                </tr>
                <?php while ($user = $result->fetch_assoc()) { ?>
                <tr onclick="showProfile('<?php echo $user['profile_picture']; ?>', '<?php echo $user['FirstName']; ?>', '<?php echo $user['LastName']; ?>', '<?php echo $user['Position']; ?>', '<?php echo $user['Department']; ?>')">
                    <td><?php echo htmlspecialchars($user['FirstName']); ?></td>
                    <td><?php echo htmlspecialchars($user['LastName']); ?></td>
                    <td><?php echo htmlspecialchars($user['Position']); ?></td>
                    <td><?php echo htmlspecialchars($user['Department']); ?></td>
                    <td><?php echo htmlspecialchars($user['Tel']); ?></td>
                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                </tr>
                <?php } ?>
            </table>
        </div>
    </div>
    
    <script>
        function showProfile(image, firstName, lastName, position, department) {
            document.getElementById("profileImage").src = image ? image : "default-profile.png";
            document.getElementById("profileName").innerText = firstName + " " + lastName;
            document.getElementById("profilePosition").innerText = "ตำแหน่ง: " + position;
            document.getElementById("profileDepartment").innerText = "แผนก: " + department;
            document.getElementById("profileBox").style.display = "block";
            document.getElementById("tableContainer").style.marginLeft = "20px";
        }
        
        function closeProfile() {
            document.getElementById("profileBox").style.display = "none";
            document.getElementById("tableContainer").style.marginLeft = "0";
        }
    </script>
</body>
</html>
