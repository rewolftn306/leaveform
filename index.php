<?php
// index.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

include('connect.php');

// ดึงข้อมูลผู้ใช้
$user_name = $_SESSION['Username'];

$stmt = $conn->prepare("SELECT firstname, lastname, role FROM users WHERE username = ?");
$stmt->bind_param("s", $user_name);
$stmt->execute();
$result = $stmt->get_result();

$firstname = '';
$lastname = '';
$role = '';

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstname = htmlspecialchars($row['firstname']);
    $lastname = htmlspecialchars($row['lastname']);
    $role = htmlspecialchars($row['role']);
}
$stmt->close();

// ดึงข้อมูลสำหรับกราฟ
$chartData = [
    'sick_leave' => 0,
    'personal_leave' => 0,
    'vacation_leave' => 0,
];

$sql = "
    SELECT lt.LeaveName AS leave_type, COUNT(*) as count
    FROM leaveapplications la
    INNER JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
    GROUP BY lt.LeaveName
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
            // เพิ่มกรณีสำหรับประเภทการลาอื่นๆ ถ้ามี
        }
    }
} else {
    error_log("Chart Data Fetch Failed: " . $conn->error);
}

// ดึงข้อมูลสำหรับตาราง
$tableData = [];
$sql = "
    SELECT 
        e.Name AS name,
        SUM(CASE WHEN lt.LeaveName = 'ลาป่วย' THEN 1 ELSE 0 END) AS sick_leave,
        SUM(CASE WHEN lt.LeaveName = 'ลากิจส่วนตัว' THEN 1 ELSE 0 END) AS personal_leave,
        SUM(CASE WHEN lt.LeaveName = 'ลาพักผ่อน' THEN 1 ELSE 0 END) AS vacation_leave,
        COUNT(la.ApplicationID) AS total_leave
    FROM employees e
    LEFT JOIN leaveapplications la ON e.EmployeeID = la.EmployeeID
    LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
    GROUP BY e.Name
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tableData[] = [
            'name' => htmlspecialchars($row['name']),
            'sick_leave' => (int)$row['sick_leave'],
            'personal_leave' => (int)$row['personal_leave'],
            'vacation_leave' => (int)$row['vacation_leave'],
            'total_leave' => (int)$row['total_leave'],
        ];
    }
} else {
    error_log("Table Data Fetch Failed: " . $conn->error);
}

$conn->close();

$jsonChartData = json_encode(array_values($chartData));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถิติการลางาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f9f9f9;
            padding-top: 70px;
        }
        .navbar {
            margin-bottom: 20px;
        }
        .profile-section {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #ddd;
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
        .buttons a {
            margin: 5px 0;
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
        table th, table td {
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">ระบบสถิติการลางาน</a>
            <div class="d-flex">
                <span class="navbar-text me-3">สวัสดี, <?= htmlspecialchars($user_name); ?></span>
                <a href="logout.php" class="btn btn-outline-light">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">

        <!-- Profile Section -->
        <div class="profile-section">
            <div class="profile-details">
                <h4>ชื่อ: <?= $firstname . ' ' . $lastname; ?></h4>
                <p>ตำแหน่ง: 
                    <?php 
                        switch ($role) {
                            case 'Employee':
                                echo 'พนักงาน';
                                break;
                            case 'Director':
                                echo 'อธิบดี';
                                break;
                            case 'Admin':
                                echo 'ผู้ดูแลระบบ';
                                break;
                            default:
                                echo 'ไม่ระบุ';
                        }
                    ?>
                </p>
            </div>
            <div class="buttons">
                <a href="inputform.php" class="btn btn-primary">ยื่นแบบฟอร์มการลา</a>
                <?php if ($role === 'Director' || $role === 'Admin'): ?>
                    <a href="approve_leave.php" class="btn btn-success">อนุมัติการลา</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart Section -->
        <?php if (array_sum($chartData) > 0): ?>
            <div class="chart-container">
                <canvas id="leaveChart" aria-label="กราฟสถิติการลางาน" role="img"></canvas>
            </div>
        <?php else: ?>
            <p class="text-center">ไม่มีข้อมูลการลางานเพื่อแสดงกราฟ</p>
        <?php endif; ?>

        <!-- Table Section -->
        <div class="table-container">
            <h3 class="text-center">ตารางสรุปการลางาน</h3>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ชื่อพนักงาน</th>
                        <th>ลาป่วย (ครั้ง)</th>
                        <th>ลากิจส่วนตัว (ครั้ง)</th>
                        <th>ลาพักร้อน (ครั้ง)</th>
                        <th>รวมทั้งหมด (ครั้ง)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tableData)): ?>
                        <?php foreach ($tableData as $row): ?>
                            <tr>
                                <td><?= $row['name']; ?></td>
                                <td><?= $row['sick_leave']; ?></td>
                                <td><?= $row['personal_leave']; ?></td>
                                <td><?= $row['vacation_leave']; ?></td>
                                <td><?= $row['total_leave']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">ไม่มีข้อมูลการลางาน</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Bootstrap JS และ Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (array_sum($chartData) > 0): ?>
        <script>
            const ctx = document.getElementById('leaveChart').getContext('2d');
            const leaveChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน'],
                    datasets: [{
                        label: 'จำนวนการลา',
                        data: <?= $jsonChartData; ?>,
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
        </script>
    <?php endif; ?>
    
</body>
</html>
