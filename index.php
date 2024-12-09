<?php
ob_start();
session_start();

// ตรวจสอบสถานะการล็อกอิน
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

$user_name = $_SESSION['Username'];

// เชื่อมต่อฐานข้อมูล
include('connect.php');

if ($conn->connect_error) {
    echo "ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $conn->connect_error;
    exit;
}

// ดึงข้อมูลตำแหน่งจากตาราง users
$sql = "SELECT firstname, lastname, role FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_name);
$stmt->execute();
$result = $stmt->get_result();
$firstname = '';
$lastname = '';
$role = '';

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstname = $row['firstname'];
    $lastname = $row['lastname'];
    $role = $row['role'];
}
$stmt->close();

// ดึงข้อมูลสำหรับกราฟ
$chartData = [
    'sick_leave' => 0,
    'personal_leave' => 0,
    'vacation_leave' => 0,
];

$sql = "
    SELECT leave_type, COUNT(*) as count
    FROM leaveapplications
    GROUP BY leave_type
";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row['leave_type'] == 'ลาป่วย') {
            $chartData['sick_leave'] = $row['count'];
        } elseif ($row['leave_type'] == 'ลากิจ') {
            $chartData['personal_leave'] = $row['count'];
        } elseif ($row['leave_type'] == 'ลาพักร้อน') {
            $chartData['vacation_leave'] = $row['count'];
        }
    }
}
$jsonChartData = json_encode(array_values($chartData));

// ดึงข้อมูลสำหรับตาราง
$tableData = [];
$sql = "
    SELECT 
        employees.Name AS name,
        SUM(CASE WHEN leave_type = 'ลาป่วย' THEN 1 ELSE 0 END) AS sick_leave,
        SUM(CASE WHEN leave_type = 'ลากิจ' THEN 1 ELSE 0 END) AS personal_leave,
        SUM(CASE WHEN leave_type = 'ลาพักร้อน' THEN 1 ELSE 0 END) AS vacation_leave,
        COUNT(*) AS total_leave
    FROM leaveapplications
    INNER JOIN employees ON leaveapplications.empid = employees.EmployeeID
    GROUP BY employees.Name
";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tableData[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถิติการลางาน</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        /* แถบผู้ใช้งานด้านบน */
        .user-bar {
            background-color: #333;
            color: white;
            padding: 10px 0;
            text-align: center;
            font-size: 16px;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .user-bar a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
        }

        .user-bar a:hover {
            text-decoration: underline;
        }

        .main-container {
            width: 90%;
            max-width: 1200px;
            margin: 80px auto 20px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
        }

        .profile-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }

        .profile-section img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .profile-details {
            line-height: 1.2;
        }

        .profile-details h4 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }

        .profile-details p {
            margin: 0;
            font-size: 14px;
            color: #666;
        }

        .btn-submit, .btn-approve {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-submit:hover, .btn-approve:hover {
            background-color: #0056b3;
        }

        .chart-container {
            width: 50%; /* ปรับขนาดความกว้างของกราฟ */
            margin: 20px auto;
        }

        #leaveChart {
            width: 100% !important; /* ทำให้กราฟขยายตามขนาดของ container */
            height: 250px !important; /* ปรับความสูงของกราฟ */
        }

        .table-container {
            margin-top: 20px;
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        table th {
            background-color: #007bff;
            color: white;
        }

        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tr:hover {
            background-color: #ddd;
        }

        .logout {
            text-align: center;
            margin: 20px;
        }

        .logout a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- แถบผู้ใช้งาน -->
    <div class="user-bar">
        <span>สวัสดี, <?= htmlspecialchars($user_name); ?> | <a href="logout.php">ออกจากระบบ</a></span>
    </div>

    <div class="main-container">
        <div class="header">ระบบสถิติการลางาน</div>

        <!-- ข้อมูลโปรไฟล์ -->
        <div class="profile-section">
            <div class="profile-details">
                <h4>ชื่อ: <?= htmlspecialchars($firstname . ' ' . $lastname); ?></h4>
                <p>ตำแหน่ง: <?= $role === 'Employee' ? 'พนักงาน' : ($role === 'Director' ? 'อธิบดี' : 'ไม่ระบุ'); ?></p>
            </div>
            <div>
                <a href="inputform.php" class="btn-submit">ยื่นแบบฟอร์มการลา</a>
                <?php if ($role == 'Director') : ?>
                    <a href="approve_leave.php" class="btn-approve">อนุมัติการลา</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- กราฟแสดงสถิติ -->
        <div class="chart-container">
        <canvas id="leaveChart"></canvas>
        </div>


        <!-- ตารางสรุปข้อมูลการลางาน -->
        <div class="table-container">
            <h3>ตารางสรุปการลางาน</h3>
            <table>
                <thead>
                    <tr>
                        <th>ชื่อพนักงาน</th>
                        <th>ลาป่วย (ครั้ง)</th>
                        <th>ลากิจ (ครั้ง)</th>
                        <th>ลาพักร้อน (ครั้ง)</th>
                        <th>รวมทั้งหมด (ครั้ง)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tableData)) : ?>
                        <?php foreach ($tableData as $row) : ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= $row['sick_leave']; ?></td>
                                <td><?= $row['personal_leave']; ?></td>
                                <td><?= $row['vacation_leave']; ?></td>
                                <td><?= $row['total_leave']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">ไม่มีข้อมูลการลางาน</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // กราฟแสดงสถิติการลางาน
        var ctx = document.getElementById('leaveChart').getContext('2d');
        var leaveChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['ลาป่วย', 'ลากิจ', 'ลาพักร้อน'],
                datasets: [{
                    label: 'จำนวนการลา',
                    data: <?= $jsonChartData ?>,
                    backgroundColor: ['#ff7f7f', '#ffcc00', '#99ccff'],
                    borderColor: ['#ff4d4d', '#ff9900', '#6699cc'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
