<?php
session_start();

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

include('connect.php');

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ดึงข้อมูลผู้ใช้
$user_name = $_SESSION['Username'];
$stmt = $conn->prepare("SELECT firstname, lastname, role, profile_picture FROM users WHERE username = ?");
$stmt->bind_param("s", $user_name);
$stmt->execute();
$result = $stmt->get_result();

$firstname = '';
$lastname = '';
$role = '';
$profile_picture = ''; // ตัวแปรสำหรับรูปโปรไฟล์

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstname = htmlspecialchars($row['firstname']);
    $lastname = htmlspecialchars($row['lastname']);
    $role = htmlspecialchars($row['role']);
    $profile_picture = $row['profile_picture']; // ดึงรูปโปรไฟล์
} else {
    echo "ไม่พบข้อมูลผู้ใช้";
    exit;
}

$stmt->close();

// คำสั่ง SQL สำหรับดึงข้อมูลการลา
if ($role === 'Admin' || $role === 'Director') {
    $sql = "SELECT u.UserID, u.FirstName, u.LastName, IFNULL(lt.LeaveName, 'ไม่ระบุ') AS leave_type, 
            la.StartDate, la.EndDate, la.ApprovalStatus, la.Remarks, la.ApplicationID 
            FROM users u
            LEFT JOIN leaveapplications la ON u.UserID = la.EmployeeID
            LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
            WHERE la.LeaveTypeID IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
} else {
    $sql = "SELECT u.FirstName, u.LastName, IFNULL(lt.LeaveName, 'ไม่ระบุ') AS leave_type, 
            la.StartDate, la.EndDate, la.ApprovalStatus, la.Remarks, la.ApplicationID 
            FROM users u
            LEFT JOIN leaveapplications la ON u.UserID = la.EmployeeID
            LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
            WHERE u.Username = ? AND la.LeaveTypeID IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_name);
    $stmt->execute();
}

$result = $stmt->get_result();

$tableData = [];
$chartData = [];
$leaveTypes = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // คำนวณจำนวนวันลา
        $start_date = new DateTime($row['StartDate']);
        $end_date = new DateTime($row['EndDate']);
        $interval = $start_date->diff($end_date);
        $leave_days = $interval->days; // นับจำนวนวันไม่รวมวันเริ่ม
        if ($start_date != $end_date) {
            $leave_days = $interval->days; // ถ้า StartDate ไม่เหมือน EndDate จะคำนวณจำนวนวัน
        } else {
            $leave_days = 1; // ถ้า StartDate กับ EndDate ตรงกัน ให้ถือว่าเป็น 1 วัน
        }

        // เพิ่มข้อมูลลงในตาราง
        $tableData[] = [
            'user_id' => $row['UserID'] ?? null,
            'name' => htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']),
            'leave_type' => htmlspecialchars($row['leave_type']),
            'start_date' => $row['StartDate'],
            'end_date' => $row['EndDate'],
            'approval_status' => htmlspecialchars($row['ApprovalStatus']),
            'remarks' => htmlspecialchars($row['Remarks']),
            'application_id' => $row['ApplicationID'],
            'leave_days' => $leave_days,
        ];

        // เก็บประเภทการลาเพื่อกราฟ
        if (!in_array($row['leave_type'], $leaveTypes)) {
            $leaveTypes[] = $row['leave_type'];
        }

        // คำนวณจำนวนวันลาในกราฟ
        if (!isset($chartData[$row['leave_type']])) {
            $chartData[$row['leave_type']] = 0;
        }
        $chartData[$row['leave_type']] += $leave_days;
    }
} else {
    error_log("Data Fetch Failed: " . $conn->error);
}

$conn->close();

// แปลงข้อมูลกราฟให้เป็น JSON
$chartLabels = json_encode(array_map(function ($leave) {
    return $leave;
}, array_values($leaveTypes)));
$chartValues = json_encode(array_values($chartData));
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
            padding: 25px 0;
            border-bottom: 1px solid #ddd;
        }

        .profile-details {
            display: flex;
            align-items: center;
        }

        .profile-details img {
            border-radius: 50%;
            width: 100px;
            height: 100px;
            margin-right: 20px;
        }

        .profile-details h4 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .profile-details p {
            margin: 5px 0 0 0;
            font-size: 18px;
            color: #666;
        }

        .buttons a {
            margin: 5px 0;
        }

        .chart-container {
            width: 100%;
            max-width: 500px;
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

        table th,
        table td {
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
                <!-- รูปโปรไฟล์จากฐานข้อมูล -->
                <?php if ($profile_picture): ?>
                    <img src="<?= htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                <?php else: ?>
                    <img src="default_profile_picture.jpg" alt="Default Profile Picture">
                <?php endif; ?>

                <div>
                    <h4>ชื่อ: <?= $firstname . ' ' . $lastname; ?></h4>
                    <p>ตำแหน่ง:
                        <?php
                        switch ($role) {
                            case 'Employee':
                                echo 'พนักงาน';
                                break;
                            case 'Director':
                                echo 'หัวหน้า';
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
            </div>
            <div class="buttons">
                <?php if ($role === 'Employee'): ?>
                    <a href="inputform.php" class="btn btn-primary">ยื่นแบบฟอร์มการลา</a>
                <?php endif; ?>
                <?php if ($role === 'Director' || $role === 'Admin'): ?>
                    <a href="approve_leave.php" class="btn btn-success">อนุมัติการลา</a>
                <?php endif; ?>
                <?php if ($role === 'Admin'): ?>
                    <a href="manage_users.php" class="btn btn-info">จัดการผู้ใช้งานทั้งหมด</a>
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
            <h3 class="text-center">
                <?php
                if ($role === 'Admin' || $role === 'Director') {
                    echo 'ตารางสรุปการลางานของลูกจ้าง';
                } else {
                    echo 'ตารางสรุปการลางาน';
                }
                ?>
            </h3>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อ</th>
                        <th>ประเภทการลา</th>
                        <th>วันที่เริ่ม-วันที่สิ้นสุด</th>
                        <th>สถานะ</th>
                        <th>จำนวนวันลา</th>
                        <th>รายละเอียด</th>
                        <th>พิมพ์ PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tableData)): ?>
                        <?php foreach ($tableData as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= $row['name']; ?></td>
                                <td><?= $row['leave_type']; ?></td>
                                <td><?= $row['start_date'] . ' - ' . $row['end_date']; ?></td>
                                <td><?= $row['approval_status']; ?></td>
                                <td><?= $row['leave_days']; ?> วัน</td>
                                <td>
                                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#leaveDetailModal"
                                        data-name="<?= $row['name']; ?>" data-start="<?= $row['start_date']; ?>"
                                        data-end="<?= $row['end_date']; ?>" data-leave-type="<?= $row['leave_type']; ?>"
                                        data-status="<?= $row['approval_status']; ?>" data-remarks="<?= $row['remarks']; ?>"
                                        data-application-id="<?= $row['application_id']; ?>">
                                        ดูรายละเอียด
                                    </button>
                                </td>
                                <td>
                                    <a href="generate_pdf.php?id=<?= $row['application_id']; ?>" class="btn btn-primary">พิมพ์
                                        PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">ไม่มีข้อมูลการลางาน</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal for detailed leave info -->
    <div class="modal fade" id="leaveDetailModal" tabindex="-1" aria-labelledby="leaveDetailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveDetailModalLabel">รายละเอียดการลา</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>ประเภทการลา:</strong> <span id="modal-leave-type"></span></p>
                    <p><strong>วันที่ลา:</strong> <span id="modal-start-date"></span> ถึง <span
                            id="modal-end-date"></span></p>
                    <p><strong>สถานะ:</strong> <span id="modal-status"></span></p>
                    <p><strong>หมายเหตุ:</strong> <span id="modal-remarks"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" class="btn btn-primary" id="printDocumentButton">พิมพ์ PDF</button>
                    <button type="button" class="btn btn-danger" id="cancelLeaveButton"
                        style="display: none;">ยกเลิกการลา</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Graph generation
        const ctx = document.getElementById('leaveChart').getContext('2d');
        const leaveChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= $chartLabels; ?>, // labels from leavetypes
                datasets: [{
                    label: 'จำนวนวันลา',
                    data: <?= $chartValues; ?>, // data from leave applications
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
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y + ' วัน'; // แสดงจำนวนวันลา
                            }
                        }
                    }
                }
            }
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var leaveDetailModal = document.getElementById('leaveDetailModal');

            if (leaveDetailModal) {
                // เมื่อ Modal เปิดขึ้น
                leaveDetailModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var name = button.getAttribute('data-name');
                    var start = button.getAttribute('data-start');
                    var end = button.getAttribute('data-end');
                    var leaveType = button.getAttribute('data-leave-type');
                    var status = button.getAttribute('data-status');
                    var remarks = button.getAttribute('data-remarks');
                    var applicationId = button.getAttribute('data-application-id');

                    // ใส่ค่าลงใน Modal
                    document.getElementById('modal-leave-type').textContent = leaveType;
                    document.getElementById('modal-start-date').textContent = start;
                    document.getElementById('modal-end-date').textContent = end;
                    document.getElementById('modal-status').textContent = status;
                    document.getElementById('modal-remarks').textContent = remarks ? remarks : 'ไม่มี';

                    var cancelLeaveButton = document.getElementById('cancelLeaveButton');
                    var printDocumentButton = document.getElementById('printDocumentButton');

                    // ถ้า "รออนุมัติ" ให้แสดงปุ่มยกเลิก
                    if (status === 'รออนุมัติ') {
                        cancelLeaveButton.style.display = 'inline-block';
                        cancelLeaveButton.setAttribute('data-application-id', applicationId);
                    } else {
                        cancelLeaveButton.style.display = 'none';
                    }

                    // กำหนด application ID สำหรับปุ่มพิมพ์ PDF
                    printDocumentButton.setAttribute('data-application-id', applicationId);
                });

                // ฟังก์ชันปุ่ม "ยกเลิกการลา"
                document.getElementById('cancelLeaveButton').addEventListener('click', function () {
                    var applicationId = this.getAttribute('data-application-id');
                    if (confirm("คุณต้องการยกเลิกการลานี้ใช่หรือไม่?")) {
                        window.location.href = 'cancel_leave.php?id=' + applicationId;
                    }
                });

                // ฟังก์ชันปุ่ม "พิมพ์ PDF" ใน Modal
                document.getElementById('printDocumentButton').addEventListener('click', function () {
                    var applicationId = this.getAttribute('data-application-id');
                    if (applicationId) {
                        window.location.href = 'generate_pdf.php?id=' + applicationId;
                    } else {
                        alert("ไม่พบข้อมูลสำหรับพิมพ์เอกสาร");
                    }
                });
            }
        });
    </script>

</body>

</html>