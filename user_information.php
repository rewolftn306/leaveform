<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบบทบาทของผู้ใช้
$allowed_roles = ['Admin', 'Director', 'Leader'];
if (!in_array($_SESSION['Role'], $allowed_roles)) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

include('connect.php');

// สร้าง CSRF Token หากยังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ค้นหาข้อมูลการลา
$search_term = '';
$status_filter = '';
$params = [];
$types = '';
$where_clauses = [];

// ใช้ GET สำหรับการค้นหาและกรอง
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['search_term']) && !empty(trim($_GET['search_term']))) {
        $search_term = '%' . trim($_GET['search_term']) . '%';
        $where_clauses[] = "u.FirstName LIKE ? OR u.LastName LIKE ?";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= 'ss';
    }

    if (isset($_GET['approval_status']) && $_GET['approval_status'] !== '') {
        $status_filter = $_GET['approval_status'];
        $where_clauses[] = "la.ApprovalStatus = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
}

// สร้างคำสั่ง SQL พร้อมการค้นหาและกรอง
$sql = "SELECT 
            u.UserID, 
            u.FirstName, 
            u.LastName, 
            u.Position
        FROM users u
        LEFT JOIN leaveapplications la ON u.UserID = la.UserID
        WHERE 1";

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}

$sql .= " GROUP BY u.UserID ORDER BY u.FirstName, u.LastName";  // Group by UserID to ensure unique users

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    $stmt->close();
}

// Fetch leave records for the modal view
$leave_records = [];
if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];

    // Get all leave records for the selected user
    $sql = "SELECT 
                la.ApplicationID, 
                la.LeaveTypeID, 
                la.StartDate, 
                la.EndDate, 
                la.ApprovalStatus, 
                la.Remarks, 
                lt.LeaveName
            FROM leaveapplications la
            JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
            WHERE la.UserID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $leave_records[] = $row;
    }
    $stmt->close();
}

// Fetch leave conditions
$leave_conditions = [];
$sql_conditions = "SELECT * FROM leaveconditions";
$conditions_result = $conn->query($sql_conditions);
while ($row = $conditions_result->fetch_assoc()) {
    // ตรวจสอบว่า $row['LeaveTypeID'] ถูกต้องหรือไม่ ก่อนที่จะใช้เป็นคีย์
    if (!isset($leave_conditions[$row['LeaveTypeID']])) {
        $leave_conditions[$row['LeaveTypeID']] = [];
    }
    $leave_conditions[$row['LeaveTypeID']][] = $row;
}

// คำนวณสถานะการได้รับค่าจ้าง
function calculateStatus($leave_type, $days, $leave_conditions) {
    $status = 'ไม่ได้รับค่าจ้าง'; // Default status

    // ตรวจสอบว่า leave_conditions[$leave_type] มีข้อมูลหรือไม่
    if (isset($leave_conditions[$leave_type]) && is_array($leave_conditions[$leave_type])) {
        foreach ($leave_conditions[$leave_type] as $condition) {
            if (strpos($condition['ConditionDescription'], 'ได้รับค่าจ้าง') !== false) {
                // ตรวจสอบเงื่อนไขสำหรับการได้รับค่าจ้าง
                if ($days <= 60) {
                    $status = 'ได้รับค่าจ้าง';
                } else {
                    $status = 'ไม่ได้รับค่าจ้าง';
                }
            }
        }
    }
    return $status;
}


// Move the database connection close here, after all queries have been executed.
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลการลา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <span class="navbar-brand">ระบบการลาของบุคลากร มหาวิทยาลัยมหาสารคาม</span>
            <div class="d-flex">
                <span class="navbar-text me-3">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['Username']); ?></span>
                <form method="POST" action="logout.php" class="d-inline">
                    <button type="submit" name="logout" class="btn btn-outline-light">ออกจากระบบ</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container mt-5">

        <!-- Search and Filter Form -->
        <form method="GET" style="margin-top: 70px;">
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search_term" class="form-control" placeholder="ค้นหาชื่อพนักงาน..."
                            value="<?= isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="approval_status" class="form-select">
                        <option value="">-- เลือกสถานะการอนุมัติ --</option>
                        <option value="Pending" <?= (isset($_GET['approval_status']) && $_GET['approval_status'] === 'Pending') ? 'selected' : ''; ?>>รอดำเนินการ</option>
                        <option value="Approved" <?= (isset($_GET['approval_status']) && $_GET['approval_status'] === 'Approved') ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                        <option value="Rejected" <?= (isset($_GET['approval_status']) && $_GET['approval_status'] === 'Rejected') ? 'selected' : ''; ?>>ถูกปฏิเสธ</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary" type="submit">ค้นหา</button>
                    <a href="user_information.php" class="btn btn-secondary w-20">รีเซ็ต</a>
                </div>
            </div>
        </form>

        <!-- Leaves Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ชื่อ</th>
                        <th>ตำแหน่ง</th>
                        <th>สถานะการได้รับค่าจ้าง</th>
                        <th>การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="4" class="text-center">ไม่พบข้อมูล</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></td>
                                <td><?= htmlspecialchars($user['Position']); ?></td>
                                <td>
                                    <?php
                                    // คำนวณจำนวนวันลาในแต่ละประเภทจาก $leave_records
                                    $leave_data = [
                                        'sickLeave' => 0,
                                        'personalLeave' => 0,
                                        'vacationLeave' => 0,
                                        'otherLeave' => 0
                                    ];

                                    foreach ($leave_records as $leave) {
                                        $startDate = new DateTime($leave['StartDate']);
                                        $endDate = new DateTime($leave['EndDate']);
                                        $diff = $startDate->diff($endDate);
                                        $leave_days = $diff->days;

                                        // Update leave type count
                                        switch ($leave['LeaveTypeID']) {
                                            case 1: // ลาป่วย
                                                $leave_data['sickLeave'] += $leave_days;
                                                break;
                                            case 2: // ลากิจส่วนตัว
                                                $leave_data['personalLeave'] += $leave_days;
                                                break;
                                            case 3: // ลาพักผ่อน
                                                $leave_data['vacationLeave'] += $leave_days;
                                                break;
                                            default: // ประเภทการลาอื่นๆ
                                                $leave_data['otherLeave'] += $leave_days;
                                                break;
                                        }
                                    }

                                    // คำนวณสถานะการได้รับค่าจ้าง
                                    $status = 'ไม่ได้รับค่าจ้าง';  // Default status
                                    foreach ($leave_data as $leave_type => $days) {
                                        $leave_type_id = 0;
                                        switch ($leave_type) {
                                            case 'sickLeave':
                                                $leave_type_id = 1;
                                                break;
                                            case 'personalLeave':
                                                $leave_type_id = 2;
                                                break;
                                            case 'vacationLeave':
                                                $leave_type_id = 3;
                                                break;
                                        }

                                        // คำนวณสถานะการได้รับค่าจ้างจากเงื่อนไข
                                        $status = calculateStatus($leave_type_id, $days, $leave_conditions);
                                    }
                                    ?>
                                    <span><?= "ลาป่วย: " . $leave_data['sickLeave'] . " วัน"; ?></span><br>
                                    <span><?= "ลากิจส่วนตัว: " . $leave_data['personalLeave'] . " วัน"; ?></span><br>
                                    <span><?= "ลาพักผ่อน: " . $leave_data['vacationLeave'] . " วัน"; ?></span><br>
                                    <span>สถานะการได้รับค่าจ้าง: <?= $status; ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#remarkModal<?= $user['UserID']; ?>"
                                        data-userid="<?= $user['UserID']; ?>">
                                        ดูรายละเอียด
                                    </button>
                                    <!-- Modal for User Profile and Leave Details -->
                                    <div class="modal fade" id="remarkModal<?= $user['UserID']; ?>" tabindex="-1"
                                        aria-labelledby="remarkModalLabel<?= $user['UserID']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="remarkModalLabel<?= $user['UserID']; ?>">รายละเอียดการลา</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <h6>ชื่อ: <?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></h6>
                                                    <p>ตำแหน่ง: <?= htmlspecialchars($user['Position']); ?></p>
                                                    <!-- Leave Type Pie Chart -->
                                                    <canvas id="leaveChart<?= $user['UserID']; ?>" width="200" height="200"></canvas>
                                                    <script>
                                                        var leaveData = {
                                                            sickLeave: <?= $leave_data['sickLeave']; ?>,
                                                            personalLeave: <?= $leave_data['personalLeave']; ?>,
                                                            vacationLeave: <?= $leave_data['vacationLeave']; ?>
                                                        };

                                                        var ctx = document.getElementById('leaveChart<?= $user['UserID']; ?>').getContext('2d');
                                                        var chart = new Chart(ctx, {
                                                            type: 'pie',
                                                            data: {
                                                                labels: ['ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน'],
                                                                datasets: [{
                                                                    label: 'ประเภทการลา',
                                                                    data: [leaveData.sickLeave, leaveData.personalLeave, leaveData.vacationLeave],
                                                                    backgroundColor: ['#FF5733', '#33FF57', '#3357FF'],
                                                                    hoverOffset: 4
                                                                }]
                                                            },
                                                            options: {
                                                                responsive: true,
                                                                plugins: {
                                                                    legend: {
                                                                        position: 'top',
                                                                    }
                                                                }
                                                            }
                                                        });
                                                    </script>
                                                    <!-- Leave History -->
                                                    <h5>ประวัติการลา</h5>
                                                    <table class="table">
                                                        <thead>
                                                            <tr>
                                                                <th>ประเภทการลา</th>
                                                                <th>วันที่เริ่ม</th>
                                                                <th>วันที่สิ้นสุด</th>
                                                                <th>สถานะ</th>
                                                                <th>จำนวนวันลา</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($leave_records as $leave): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($leave['LeaveName']); ?></td>
                                                                    <td><?= htmlspecialchars($leave['StartDate']); ?></td>
                                                                    <td><?= htmlspecialchars($leave['EndDate']); ?></td>
                                                                    <td><?= htmlspecialchars($leave['ApprovalStatus']); ?></td>
                                                                    <td>
                                                                        <?php
                                                                        $startDate = new DateTime($leave['StartDate']);
                                                                        $endDate = new DateTime($leave['EndDate']);
                                                                        $diff = $startDate->diff($endDate);
                                                                        echo $diff->days . ' วัน';
                                                                        ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">ปิด</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="index.php" class="btn btn-danger mt-3 w-20">ย้อนกลับ</a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
