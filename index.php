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
$profile_picture = '';

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstname = htmlspecialchars($row['firstname']);
    $lastname = htmlspecialchars($row['lastname']);
    $role = htmlspecialchars($row['role']);
    $profile_picture = $row['profile_picture'];
} else {
    echo "ไม่พบข้อมูลผู้ใช้";
    exit;
}

$stmt->close();

// สำหรับ Admin หรือ Director
if ($role === 'Admin' || $role === 'Director' || $role === 'Leader' || $role === 'Leader2' || $role === 'Leader3') {
    $sql = "SELECT u.UserID, u.FirstName, u.LastName, IFNULL(lt.LeaveName, 'ไม่ระบุ') AS leave_type, 
            la.StartDate, la.EndDate, la.ApprovalStatus, la.Remarks, la.ApplicationID 
            FROM users u
            LEFT JOIN leaveapplications la ON u.UserID = la.UserID
            LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
            WHERE la.LeaveTypeID IS NOT NULL
            ORDER BY la.CreateDate DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
} else {
    $sql = "SELECT u.FirstName, u.LastName, IFNULL(lt.LeaveName, 'ไม่ระบุ') AS leave_type, 
            la.StartDate, la.EndDate, la.ApprovalStatus, la.Remarks, la.ApplicationID 
            FROM users u
            LEFT JOIN leaveapplications la ON u.UserID = la.UserID
            LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
            WHERE u.Username = ? AND la.LeaveTypeID IS NOT NULL
            ORDER BY la.CreateDate DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_name);
    $stmt->execute();
}

$result = $stmt->get_result();
// ตัวแปรเก็บข้อมูลสำหรับตาราง (ไม่กรองสถานะ)
$tableData = [];

// ตัวแปรเก็บข้อมูลกราฟ (กรองเฉพาะสถานะ "Approved")
$chartData = [];
$leaveTypes = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // คำนวณจำนวนวันลา
        $start_date = new DateTime($row['StartDate']);
        $end_date = new DateTime($row['EndDate']);
        $interval = $start_date->diff($end_date);
        $leave_days = $interval->days;

        // เพิ่มข้อมูลการลาเข้าใน $tableData (ข้อมูลทั้งหมดแสดงในตาราง)
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

        // ถ้าสถานะเป็น "Approved" ให้เพิ่มข้อมูลสำหรับกราฟ
        if ($row['ApprovalStatus'] === 'Approved') {
            if (!in_array($row['leave_type'], $leaveTypes)) {
                $leaveTypes[] = $row['leave_type'];
            }

            if (!isset($chartData[$row['leave_type']])) {
                $chartData[$row['leave_type']] = 0;
            }
            $chartData[$row['leave_type']] += $leave_days;
        }
    }
} else {
    error_log("Data Fetch Failed: " . $conn->error);
}

$conn->close();

// แปลงข้อมูลกราฟให้เป็น JSON (เฉพาะข้อมูลที่มีสถานะ "Approved")
$chartLabels = json_encode(array_map(function ($leave) {
    return $leave;
}, array_values($leaveTypes)));

$chartValues = json_encode(array_values($chartData)); ?>


<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถิติการลางาน</title>
    <link id="favicon" rel="icon" href="default-favicon.ico" type="image/x-icon">
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
            display: flex;
            justify-content: center;
            /* จัดตำแหน่งกราฟให้อยู่ตรงกลางแนวนอน */
            align-items: center;
            /* จัดตำแหน่งกราฟให้อยู่ตรงกลางแนวตั้ง */
            width: 105%;
            padding: 0 10px;
        }

        #leaveChart {
            width: 10%;
            height: 600px !important;
        }

        .legend-container {
            width: 20%;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .color-box {
            width: 20px;
            height: 20px;
            margin-right: 10px;
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
                                echo 'อธิบดี';
                                break;
                            case 'Leader':
                                echo 'หัวหน้า';
                                break;
                            case 'Leader2':
                                echo 'หัวหน้า2';
                                break;
                            case 'Leader3':
                                echo 'หัวหน้า3';
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
                    <a href="edit_user.php?userid=<?= $_SESSION['UserID']; ?>"
                        class="btn btn-primary">แก้ไขข้อมูลส่วนตัว</a>
                <?php endif; ?>
                <?php if ($role === 'Director' || $role === 'Admin' || $role === 'Leader' || $role === 'Leader2' || $role === 'Leader3'): ?>
                    <a href="approve_leave.php" class="btn btn-success">อนุมัติการลา</a>
                <?php endif; ?>
                <?php if ($role === 'Admin'): ?>
                    <a href="manage_users.php" class="btn btn-info">จัดการผู้ใช้งานทั้งหมด</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart Section -->
        <?php if ($role === 'Employee'): ?>
                <?php include('user_leave_dashboard.php'); ?>
            <?php endif; ?>
        <!-- Table Section -->
        <div class="table-container">
            <h3 class="text-center">
                <?php
                if ($role === 'Admin' || $role === 'Director' || $role === 'Leader' || $role === 'Leader2' || $role === 'Leader3') {
                    echo 'ตารางการลางานของลูกจ้าง';
                } else {
                    echo 'ตารางการลางาน';
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
                <tbody id="tableBody">
                    <?php if (!empty($tableData)): ?>
                        <?php foreach ($tableData as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= $row['name']; ?></td>
                                <td><?= $row['leave_type']; ?></td>
                                <td><?= $row['start_date'] . ' - ' . $row['end_date']; ?></td>
                                <td id="leave-<?= $row['application_id']; ?>" class="status"><?= $row['approval_status']; ?>
                                </td>
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
                                    <a href="view_pdf.php?id=<?= $row['application_id']; ?>" class="btn btn-primary">พิมพ์
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
        const chartColors = [
            '#4a90e2', '#8bc34a', '#9e9e9e', '#003366', '#ffcc00', // 5 สีแรก
            '#66bb6a', '#d32f2f', '#0288d1', '#7b1fa2', '#fbc02d', // 5 สีถัดไป
            '#c2185b', '#1976d2'  // 2 สีสุดท้าย
        ];

        // การแสดงกราฟแบบโดนัท (Pie Chart หรือ Doughnut Chart)
        const ctx = document.getElementById('leaveChart').getContext('2d');
        const leaveChart = new Chart(ctx, {
            type: 'pie', // ใช้กราฟวงกลมหรือกราฟโดนัท
            data: {
                labels: <?= $chartLabels; ?>, // ใช้ข้อความที่รวมประเภทการลาและจำนวนวัน
                datasets: [{
                    data: <?= $chartValues; ?>,  // ข้อมูลจำนวนวันลา
                    backgroundColor: chartColors.slice(0, <?= count($leaveTypes); ?>), // ใช้สีที่ตรงกับจำนวนประเภทการลา
                    borderColor: chartColors.slice(0, <?= count($leaveTypes); ?>), // ใช้สีเดียวกันในกราฟ
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function (tooltipItem) {
                                return tooltipItem[0].label; // แสดงชื่อประเภทการลาและจำนวนวัน
                            },
                            label: function (tooltipItem) {
                                return tooltipItem.raw + ' วัน'; // แสดงจำนวนวันลา
                            }
                        }
                    },
                    legend: {
                        position: 'bottom', // จัดตำแหน่ง legend ที่ด้านล่างของกราฟ
                        labels: {
                            font: {
                                size: 14
                            },
                            boxWidth: 20
                        }
                    }
                }
            }
        });



        // แสดงชื่อประเภทการลาและจำนวนวันลาในกราฟและข้อความ
        const leaveTypes = <?= $chartLabels; ?>;
        const leaveDays = <?= json_encode(array_values($chartData)); ?>; // เก็บข้อมูลจำนวนวันลาแต่ละประเภท
        const legendContainer = document.getElementById('leaveTypesLegend');

        // วนลูปเพื่อแสดงประเภทการลาและจำนวนวัน
        /*leaveTypes.forEach((leaveType, index) => {
            const legendItem = document.createElement('div');
            legendItem.classList.add('legend-item');

            const colorBox = document.createElement('div');
            colorBox.classList.add('color-box');
            colorBox.style.backgroundColor = chartColors[index];

            const label = document.createElement('span');
            label.innerText = `${leaveType} = "${leaveDays[index]} วัน"`; // แสดงประเภทการลาและจำนวนวัน

            legendItem.appendChild(colorBox);
            legendItem.appendChild(label);
            legendContainer.appendChild(legendItem);
        })*/;


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
                        window.location.href = 'view_pdf.php?id=' + applicationId;
                    } else {
                        alert("ไม่พบข้อมูลสำหรับพิมพ์เอกสาร");
                    }
                });
            }
        });
        // ฟังก์ชันตรวจสอบสถานะการลา
        function checkLeaveStatus() {
            fetch('get_leave_status.php')  // URL สำหรับดึงสถานะการลา
                .then(response => response.json())
                .then(data => {
                    data.forEach(leave => {
                        const row = document.querySelector(`#leave-${leave.application_id}`); // ค้นหาตาม ApplicationID
                        if (row) {
                            const statusCell = row;
                            if (statusCell && statusCell.textContent !== leave.status) {
                                // การเปลี่ยนแปลงสถานะ
                                statusCell.textContent = leave.status;
                                if (leave.status === "Approved" || leave.status === "Rejected") {
                                    showAlert(`สถานะการลาล่าสุด "${leave.leave_type}" มีสถานะเป็น: ${leave.status}`);
                                    playNotificationSound(); // เล่นเสียงแจ้งเตือน
                                    showRedDot(); // แสดงจุดแดงบนแท็บ
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Error checking leave status:', error));
        }

        // ฟังก์ชันแสดงการแจ้งเตือน
        function showAlert(message) {
            // ตรวจสอบว่า alertBox ถูกสร้างขึ้นมาหรือยัง
            console.log("Showing alert: ", message); // เพิ่มการตรวจสอบ
            const alertBox = document.createElement('div');
            alertBox.classList.add('alert', 'alert-info');
            alertBox.textContent = message;
            alertBox.style.position = "fixed";  // เพิ่มตำแหน่งที่แน่นอน
            alertBox.style.top = "90px";        // ตำแหน่งจากด้านบน
            alertBox.style.left = "50%";        // ตำแหน่งจากด้านซ้าย
            alertBox.style.transform = "translateX(-50%)";  // จัดให้แสดงอยู่ตรงกลาง
            alertBox.style.zIndex = "9999";     // ตรวจสอบว่าอยู่ด้านบนสุด

            document.body.prepend(alertBox);

            setTimeout(() => {
                alertBox.remove();
            }, 10000); // ปิดการแจ้งเตือนหลังจาก 10 วินาที
        }
        // ฟังก์ชันเล่นเสียงแจ้งเตือน
        function playNotificationSound() {
            const audio = new Audio('/leaveform/notification-sound.mp3'); // ใช้เส้นทางสัมพัทธ์จาก `htdocs`
            audio.play();
        }


        // ฟังก์ชันแสดงจุดแดงบนแท็บ
        function showRedDot() {
            // เปลี่ยนชื่อแท็บ
            const favicon = document.getElementById('favicon');
            favicon.href = 'notification-favicon.svg'; // ใช้ไอคอนที่มีจุดแดง (เปลี่ยนเป็นไฟล์ของคุณ)

            // คืนค่าชื่อแท็บหลังจาก 3 วินาที
            setTimeout(() => {
                favicon.href = 'default.svg'; // คืนค่า favicon เป็นไอคอนปกติ
            }, 5000);
        }

        // เรียกใช้ฟังก์ชันทุกๆ 5 วินาที
        setInterval(checkLeaveStatus, 5000);

    </script>
    <script>
        // ฟังก์ชันสำหรับอัปเดตกราฟ
        function updateChart() {
            // ใช้ fetch เพื่อดึงข้อมูลใหม่จากเซิร์ฟเวอร์
            fetch('get_leave_data.php')
                .then(response => response.json())
                .then(data => {
                    // อัปเดตข้อมูลในกราฟ
                    leaveChart.data.labels = data.labels;  // อัปเดต labels
                    leaveChart.data.datasets[0].data = data.values;  // อัปเดต values
                    leaveChart.update();  // อัปเดตกราฟ
                })
                .catch(error => console.error('Error updating chart:', error));
        }

        // กำหนดให้กราฟอัปเดตทุก 5 วินาที
        setInterval(updateChart, 5000);

        // เรียกใช้ฟังก์ชันทันทีเมื่อโหลดหน้า
        document.addEventListener('DOMContentLoaded', function () {
            updateChart();
        });
    </script>
</body>

</html>