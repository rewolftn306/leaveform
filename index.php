<?php
ob_start();
session_start();

// ตรวจสอบสถานะการล็อกอิน
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

$user_name = $_SESSION['Username'];

// รวมไฟล์เชื่อมต่อฐานข้อมูล
include('connect.php');  // เชื่อมต่อกับไฟล์ connect.php ที่สร้างขึ้น

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if ($conn->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ดึงข้อมูลตำแหน่งจากตาราง users ด้วย prepared statements
$sql = "SELECT firstname, lastname, role FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("การเตรียมคำสั่ง SQL ล้มเหลว: " . $conn->error);
}

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
    SELECT leavetypes.LeaveName AS leave_type, COUNT(*) as count
    FROM leaveapplications
    INNER JOIN leavetypes ON leaveapplications.LeaveTypeID = leavetypes.LeaveTypeID
    GROUP BY leavetypes.LeaveName
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        switch ($row['leave_type']) {
            case 'ลาป่วย':
                $chartData['sick_leave'] = (int)$row['count'];
                break;
            case 'ลากิจส่วนตัว':
                $chartData['personal_leave'] = (int)$row['count'];
                break;
            case 'ลาพักผ่อน':
                $chartData['vacation_leave'] = (int)$row['count'];
                break;
        }
    }
} else {
    // จัดการข้อผิดพลาดเมื่อคำสั่ง SQL ล้มเหลว
    die("เกิดข้อผิดพลาดในการดึงข้อมูลกราฟ: " . $conn->error);
}

$jsonChartData = json_encode(array_values($chartData));

// ดึงข้อมูลสำหรับตาราง
$tableData = [];
$sql = "
    SELECT 
        employees.Name AS name,
        SUM(CASE WHEN leavetypes.LeaveName = 'ลาป่วย' THEN 1 ELSE 0 END) AS sick_leave,
        SUM(CASE WHEN leavetypes.LeaveName = 'ลากิจส่วนตัว' THEN 1 ELSE 0 END) AS personal_leave,
        SUM(CASE WHEN leavetypes.LeaveName = 'ลาพักผ่อน' THEN 1 ELSE 0 END) AS vacation_leave,
        COUNT(leaveapplications.ApplicationID) AS total_leave
    FROM employees
    LEFT JOIN leaveapplications ON employees.EmployeeID = leaveapplications.EmployeeID
    LEFT JOIN leavetypes ON leaveapplications.LeaveTypeID = leavetypes.LeaveTypeID
    GROUP BY employees.Name
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tableData[] = $row;
    }
} else {
    // จัดการข้อผิดพลาดเมื่อคำสั่ง SQL ล้มเหลว
    die("เกิดข้อผิดพลาดในการดึงข้อมูลตาราง: " . $conn->error);
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
            padding: 20px;
        }

        .header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            border-radius: 5px;
        }

        .profile-section {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #ddd;
        }

        .profile-details {
            line-height: 1.5;
        }

        .profile-details h4 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .profile-details p {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #666;
        }

        .buttons {
            margin-top: 10px;
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
            margin: 5px 0;
        }

        .btn-submit:hover, .btn-approve:hover {
            background-color: #0056b3;
        }

        .chart-container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
        }

        #leaveChart {
            width: 100% !important;
            height: 250px !important;
        }

        .table-container {
            margin-top: 20px;
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 12px 15px;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .buttons {
                width: 100%;
                display: flex;
                flex-direction: column;
            }

            .btn-submit, .btn-approve {
                width: 100%;
                text-align: center;
            }

            .chart-container {
                max-width: 100%;
            }

            table {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- แถบผู้ใช้งาน -->
    <div class="user-bar">
        <span>สวัสดี, <?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?> | <a href="logout.php">ออกจากระบบ</a></span>
    </div>

    <div class="main-container">
        <div class="header">ระบบสถิติการลางาน</div>

        <!-- ข้อมูลโปรไฟล์ -->
        <div class="profile-section">
            <div class="profile-details">
                <h4>ชื่อ: <?= htmlspecialchars($firstname . ' ' . $lastname, ENT_QUOTES, 'UTF-8'); ?></h4>
                <p>ตำแหน่ง: <?= htmlspecialchars(
                    ($role === 'Employee') ? 'พนักงาน' : (($role === 'Director') ? 'อธิบดี' : 'ไม่ระบุ'),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?></p>
            </div>
            <div class="buttons">
                <a href="inputform.php" class="btn-submit">ยื่นแบบฟอร์มการลา</a>
                <?php if ($role === 'Director') : ?>
                    <a href="approve_leave.php" class="btn-approve">อนุมัติการลา</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- กราฟแสดงสถิติ -->
        <?php if (array_sum($chartData) > 0) : ?>
            <div class="chart-container">
                <canvas id="leaveChart" aria-label="กราฟสถิติการลางาน" role="img"></canvas>
            </div>
        <?php else : ?>
            <p style="text-align: center;">ไม่มีข้อมูลการลางานเพื่อแสดงกราฟ</p>
        <?php endif; ?>

        <!-- ตารางสรุปข้อมูลการลางาน -->
        <div class="table-container">
            <h3>ตารางสรุปการลางาน</h3>
            <table>
                <thead>
                    <tr>
                        <th>ชื่อพนักงาน</th>
                        <th>ลาป่วย (ครั้ง)</th>
                        <th>ลากิจส่วนตัว (ครั้ง)</th>
                        <th>ลาพักร้อน (ครั้ง)</th>
                        <th>รวมทั้งหมด (ครั้ง)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tableData)) : ?>
                        <?php foreach ($tableData as $row) : ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= (int)$row['sick_leave']; ?></td>
                                <td><?= (int)$row['personal_leave']; ?></td>
                                <td><?= (int)$row['vacation_leave']; ?></td>
                                <td><?= (int)$row['total_leave']; ?></td>
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
        <?php if (array_sum($chartData) > 0) : ?>
            // กราฟแสดงสถิติการลางาน
            var ctx = document.getElementById('leaveChart').getContext('2d');
            var leaveChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน'],
                    datasets: [{
                        label: 'จำนวนการลา',
                        data: <?= $jsonChartData ?>,
                        backgroundColor: ['#ff7f7f', '#ffcc00', '#99ccff'],
                        borderColor: ['#ff4d4d', '#ff9900', '#6699cc'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision:0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' ครั้ง';
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>
