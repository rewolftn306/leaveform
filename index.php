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
} else {
    echo "ไม่พบข้อมูลผู้ใช้";
}
$stmt->close();

// กำหนดค่าเริ่มต้นสำหรับกราฟ
$chartData = [];
$leaveTypes = [];

// กรณี Role เป็น Admin หรือ Employee
if ($role === 'Admin') {
    // ดึงข้อมูลสำหรับตารางการลาของทุกคน
    $sql = "
        SELECT 
            u.UserID, u.FirstName, u.LastName,
            IFNULL(lt.LeaveName, 'ไม่ระบุ') AS leave_type,
            la.StartDate, la.EndDate,
            la.ApprovalStatus, la.Remarks, la.ApplicationID
        FROM users u
        LEFT JOIN leaveapplications la ON u.UserID = la.EmployeeID
        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        WHERE la.LeaveTypeID IS NOT NULL
    ";
} else {
    // กรณี Role เป็น Employee ให้ดึงข้อมูลเฉพาะของตัวเอง
    $sql = "
        SELECT 
            u.FirstName, u.LastName,
            IFNULL(lt.LeaveName, 'ไม่ระบุ') AS leave_type,
            la.StartDate, la.EndDate,
            la.ApprovalStatus, la.Remarks, la.ApplicationID
        FROM users u
        LEFT JOIN leaveapplications la ON u.UserID = la.EmployeeID
        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        WHERE u.Username = ? AND la.LeaveTypeID IS NOT NULL
    ";
}

$stmt = $conn->prepare($sql);
if ($role !== 'Admin') {
    $stmt->bind_param("s", $user_name); // binding user name for Employee role
}
$stmt->execute();
$result = $stmt->get_result();

$tableData = []; // กำหนดตัวแปร $tableData เพื่อเก็บข้อมูล

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tableData[] = [
            'user_id' => $row['UserID'] ?? null, // for Admin
            'name' => htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']),
            'leave_type' => htmlspecialchars($row['leave_type']),
            'start_date' => $row['StartDate'],
            'end_date' => $row['EndDate'],
            'approval_status' => htmlspecialchars($row['ApprovalStatus']),
            'remarks' => htmlspecialchars($row['Remarks']),
            'application_id' => $row['ApplicationID'],  // แก้เป็น ApplicationID
        ];

        // เพิ่มข้อมูลสำหรับกราฟ
        if (!in_array($row['leave_type'], $leaveTypes)) {
            $leaveTypes[] = $row['leave_type']; // บันทึกประเภทการลา
        }

        if (!isset($chartData[$row['leave_type']])) {
            $chartData[$row['leave_type']] = 0; // กำหนดค่าเริ่มต้นให้เป็น 0
        }
        $chartData[$row['leave_type']] += 1;
    }
} else {
    error_log("Data Fetch Failed: " . $conn->error);
}

$conn->close();

// แปลงข้อมูลกราฟให้เป็น JSON
$chartLabels = json_encode(array_map(function($leave) {
    // เปลี่ยนชื่อประเภทการลาให้สั้นลง
    switch ($leave) {
        case 'ลาป่วย':
            return 'ลาป่วย';
        case 'ลากิจส่วนตัว':
            return 'ลากิจ';
        case 'ลาพักผ่อน':
            return 'ลาพักผ่อน';
        case 'ลาบวช/ประกอบพิธีฮัจย์':
            return 'ลาบวช';
        case 'ลาไปถือศีลและปฏิบัติธรรม':
            return 'ลาไปถือศีล';
        case 'ลาเข้ารับการตรวจเลือกหรือเข้ารับเตรียมพล':
            return 'ลาเข้ารับการตรวจเลือก';
        case 'ลาดูแลบิดาหรือมารดา':
            return 'ลาดูแลบิดา-มารดา';    
        case 'การลาคลอดบุตร':
            return 'การลาคลอดบุตร';
        case 'การลากิจเพื่อเลี้ยงดูบุตรต่อเนื่องจากการคลอดบุตร':
            return 'ลาเพื่อเลี้ยงดูบุตร';   
        case 'ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร':
            return 'ลาเพื่อแลบุตรและภรรยา';
        case 'การลาติดตามคู่สมรส':
            return 'การลาติดตามคู่สมรส';  
        default:
            return $leave;
    }
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
            <h3 class="text-center">ตารางสรุปการลางาน</h3>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อ</th>
                        <th>ประเภทการลา</th>
                        <th>วันที่เริ่ม-วันที่สิ้นสุด</th>
                        <th>สถานะ</th>
                        <th>รายละเอียด</th>
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
                                <td>
                                    <?php if ($role === 'Admin'): ?>
                                        <a href="view_user_leave.php?user_id=<?= $row['user_id']; ?>" class="btn btn-info">ดูรายละเอียด</a>
                                    <?php else: ?>
                                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#leaveDetailModal" 
                                                data-name="<?= $row['name']; ?>" 
                                                data-start="<?= $row['start_date']; ?>" 
                                                data-end="<?= $row['end_date']; ?>" 
                                                data-leave-type="<?= $row['leave_type']; ?>" 
                                                data-status="<?= $row['approval_status']; ?>" 
                                                data-remarks="<?= $row['remarks']; ?>" 
                                                data-application-id="<?= $row['application_id']; ?>">ดูรายละเอียด</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">ไม่มีข้อมูลการลางาน</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal for detailed leave info -->
    <div class="modal fade" id="leaveDetailModal" tabindex="-1" aria-labelledby="leaveDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveDetailModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" id="cancelLeaveButton" class="btn btn-danger" style="display: none;">ยกเลิกการลา</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS และ Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Graph generation
        const ctx = document.getElementById('leaveChart').getContext('2d');
        const leaveChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= $chartLabels; ?>, // labels from leavetypes
                datasets: [{
                    label: 'จำนวนการลา',
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
                            label: function(context) {
                                return context.parsed.y + ' ครั้ง';
                            }
                        }
                    }
                }
            }
        });

        // Popup Modal - Show data
        const leaveDetailModal = document.getElementById('leaveDetailModal');
        leaveDetailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            // ดึงข้อมูลจาก data-attributes ของปุ่ม
            const name = button.getAttribute('data-name');
            const start = button.getAttribute('data-start');
            const end = button.getAttribute('data-end');
            const leaveType = button.getAttribute('data-leave-type');
            const status = button.getAttribute('data-status');
            const remarks = button.getAttribute('data-remarks');
            const applicationId = button.getAttribute('data-application-id');  // Added application ID for canceling

            // แสดงข้อมูลใน Modal
            leaveDetailModal.querySelector('.modal-title').textContent = 'รายละเอียดการลา: ' + name;
            leaveDetailModal.querySelector('.modal-body').innerHTML = `
                <p><strong>ชื่อ:</strong> ${name}</p>
                <p><strong>ประเภทการลา:</strong> ${leaveType}</p>
                <p><strong>วันที่เริ่ม:</strong> ${start}</p>
                <p><strong>วันที่สิ้นสุด:</strong> ${end}</p>
                <p><strong>สถานะ:</strong> ${status}</p>
                <p><strong>หมายเหตุ:</strong> ${remarks}</p>
            `;

            // ถ้าสถานะเป็น Pending ให้แสดงปุ่ม "ยกเลิกการลา"
            if (status === 'Pending') {
                document.getElementById('cancelLeaveButton').style.display = 'inline-block';
                document.getElementById('cancelLeaveButton').setAttribute('data-application-id', applicationId);
            } else {
                document.getElementById('cancelLeaveButton').style.display = 'none';
            }
        });

        // เมื่อกดปุ่มยกเลิกการลา
        document.getElementById('cancelLeaveButton').addEventListener('click', function() {
            if (confirm('คุณแน่ใจที่จะยกเลิกการลา?')) {
                // ดึงค่า application_id จาก data-attribute
                const applicationId = document.getElementById('cancelLeaveButton').getAttribute('data-application-id');

                // ตรวจสอบว่า applicationId มีค่า
                if (!applicationId) {
                    alert('No Application ID provided!');
                    return; // ถ้าไม่มี applicationId ส่งมาก็ไม่ทำการยกเลิก
                }

                // ส่งข้อมูลไปยัง PHP เพื่อลบหรือเปลี่ยนสถานะเป็น "Cancelled"
                fetch('cancel_leave.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `application_id=${applicationId}`  // ส่ง application_id ไปที่ cancel_leave.php
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        alert('ยกเลิกการลาเรียบร้อย');
                        location.reload();  // รีเฟรชหน้าเพื่ออัพเดตข้อมูล
                    } else {
                        alert('เกิดข้อผิดพลาดในการยกเลิกการลา: ' + data); // แสดงข้อความข้อผิดพลาดที่ได้รับจาก PHP
                    }
                });
            }
        });
    </script>
</body>
</html>
