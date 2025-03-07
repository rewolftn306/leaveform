<?php
session_start();
require_once 'connect.php';

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!$conn) {
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$sql = "SELECT UserID, FirstName, LastName, Position, Department, Tel, Email, profile_picture FROM users";
$result = $conn->query($sql);
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
            background-color: #f4f4f4;
            margin: 20px;
            padding: 20px;
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

        .profile-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 500px;
            max-height: 80vh;
            overflow-y: auto;
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

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
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
            <tr onclick="showProfile(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                <td><?php echo htmlspecialchars($user['FirstName']); ?></td>
                <td><?php echo htmlspecialchars($user['LastName']); ?></td>
                <td><?php echo htmlspecialchars($user['Position']); ?></td>
                <td><?php echo htmlspecialchars($user['Department']); ?></td>
                <td><?php echo htmlspecialchars($user['Tel']); ?></td>
                <td><?php echo htmlspecialchars($user['Email']); ?></td>
            </tr>
        <?php } ?>
    </table>

    <div class="profile-modal" id="profileModal">
        <button class="close-btn" onclick="closeProfile()">×</button>
        <img id="profileImage" src="" alt="Profile Picture"
            style="width: 100px; height: 100px; border-radius: 50%; display: block; margin: auto;">
        <h3 id="profileName"></h3>
        <p id="profilePosition"></p>
        <p id="profileDepartment"></p>
        <h4>ตารางการลา</h4>
        <table id="leaveTable">
            <tr>
                <th>ประเภทการลา</th>
                <th>วันที่เริ่ม</th>
                <th>วันที่สิ้นสุด</th>
                <th>สถานะ</th>
            </tr>
        </table>
    </div>

    <script>
        function showProfile(user) {
            document.getElementById("profileImage").src = user.profile_picture ? user.profile_picture : "default-profile.png";
            document.getElementById("profileName").innerText = user.FirstName + " " + user.LastName;
            document.getElementById("profilePosition").innerText = "ตำแหน่ง: " + user.Position;
            document.getElementById("profileDepartment").innerText = "แผนก: " + user.Department;
            document.getElementById("profileModal").style.display = "block";
            fetchLeaveData(user.UserID);
        }

        function closeProfile() {
            document.getElementById("profileModal").style.display = "none";
        }

        function fetchLeaveData(userId) {
            fetch(fetch_leave.php ? UserID = ${ userId })
                .then(response => response.json())
                .then(data => {
                    let table = document.getElementById("leaveTable");
                    table.innerHTML = `
                        <tr>
                            <th>ประเภทการลา</th>
                            <th>วันที่เริ่ม</th>
                            <th>วันที่สิ้นสุด</th>
                            <th>สถานะ</th>
                        </tr>
                    `;
                    data.forEach(leave => {
                        let row = `<tr>
                            <td>${leave.LeaveTypeID}</td>

                            <td>${leave.StartDate}</td>
                            <td>${leave.EndDate}</td>
                            <td>${leave.ApprovalStatus}</td>
                        </tr>`;
                        table.innerHTML += row;
                    });
                })
                .catch(error => console.error('Error fetching leave data:', error));
        }
    </script>
</body>

</html>