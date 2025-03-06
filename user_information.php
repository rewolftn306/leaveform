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
            u.UserID, 
            u.FirstName, 
            u.LastName, 
            lt.LeaveName, 
            la.StartDate, 
            la.EndDate, 
            la.ApprovalStatus, 
            la.Remarks, 
            la.ApplicationID
        FROM users u
        LEFT JOIN leaveapplications la ON u.UserID = la.UserID
        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        WHERE u.UserID = ?
        ORDER BY la.StartDate DESC"; // เรียงลำดับตามวันที่เริ่มต้นของการลา

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // คำนวณจำนวนวันลา
        $start_date = new DateTime($row['StartDate']);
        $end_date = new DateTime($row['EndDate']);
        $interval = $start_date->diff($end_date);
        $leave_days = $interval->days;

        // หากวันที่เริ่มต้นและสิ้นสุดตรงกัน ให้ถือว่าเป็น 1 วัน
        if ($start_date == $end_date) {
            $leave_days = 1;
        }

        // เพิ่มข้อมูลการลาเข้าไปใน $leave_records
        $row['leave_days'] = $leave_days;
        $leave_records[] = $row;
    }
    $stmt->close();
}

// Fetch leave types
$leave_types = [];
$sql_leave_types = "SELECT LeaveTypeID, LeaveName FROM leavetypes";
$result_leave_types = $conn->query($sql_leave_types);
while ($row = $result_leave_types->fetch_assoc()) {
    $leave_types[$row['LeaveTypeID']] = $row['LeaveName'];
}

// Fetch leave conditions for all leave types
$leave_conditions = [];
$sql_conditions = "SELECT * FROM leaveconditions";
$conditions_result = $conn->query($sql_conditions);
while ($row = $conditions_result->fetch_assoc()) {
    if (!isset($leave_conditions[$row['LeaveTypeID']])) {
        $leave_conditions[$row['LeaveTypeID']] = [];
    }
    $leave_conditions[$row['LeaveTypeID']][] = $row;
}

// ฟังก์ชันคำนวณสถานะการได้รับค่าจ้าง
function calculateStatus($leave_type, $days)
{
    $status = 'ได้รับค่าจ้าง'; // ค่าเริ่มต้น

    // Logic for each leave type
    switch ($leave_type) {
        case 'ลาป่วย':  // Sick Leave
            $leaveLimit = 120;  // ขีดจำกัดวันลา
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } elseif ($days > 120) {  // ถ้าจำนวนวันเกิน 120 วัน
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลากิจส่วนตัว':  // Personal Leave
            $leaveLimit = 45;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาพักผ่อน':  // Vacation Leave
            $leaveLimit = 30;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาบวช':  // Ordination Leave
            $leaveLimit = 120;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาไปถือศีลและปฏิบัติธรรม':  // Meditation Leave
            $leaveLimit = 90;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาเข้ารับการตรวจเลือกหรือเข้ารับเตรียมพล':  // Military Leave
            $leaveLimit = 60;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาดูแลบิดาหรือมารดา':  // Parent Care Leave
            $leaveLimit = 5;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'การลาคลอดบุตร':  // Childbirth Leave
            $leaveLimit = 90;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'การลากิจเพื่อเลี้ยงดูบุตร':  // Childcare Leave
            $leaveLimit = 150;
            if ($days <= $leaveLimit) {
                $status = 'ไม่ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาสมรส':  // Marriage Leave
            $leaveLimit = 10;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาพักผ่อนไปต่างประเทศ':  // Foreign Vacation Leave
            $leaveLimit = 30;
            if ($days <= $leaveLimit) {
                $status = 'ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        case 'ลาติดตามคู่สมรส':  // Spouse Follow Leave
            $leaveLimit = 1440;
            if ($days <= $leaveLimit) {
                $status = 'ไม่ได้รับค่าจ้าง';
            } else {
                $status = 'ไม่ได้รับค่าจ้าง';
            }
            break;

        default:
            $status = 'ได้รับค่าจ้าง';  // Default case if leave type is not recognized
            break;
    }

    return $status;
}


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
                                    // ดึงข้อมูลการลาจากฐานข้อมูล
                                    $leave_data = [];
                                    foreach ($leave_types as $leave_type_id => $leave_name) {
                                        $leave_data[$leave_name] = 0;  // Initialize leave data
                                    }

                                    // ดึงข้อมูลการลาของผู้ใช้งาน
                                    $sql = "SELECT LeaveTypeID, StartDate, EndDate FROM leaveapplications WHERE UserID = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("i", $user['UserID']);  // $user_id คือ ID ของผู้ใช้
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    while ($leave = $result->fetch_assoc()) {
                                        $startDate = new DateTime($leave['StartDate']);
                                        $endDate = new DateTime($leave['EndDate']);
                                        $diff = $startDate->diff($endDate);
                                        $leave_days = $diff->days;

                                        // เพิ่มจำนวนวันลาตามประเภทที่ตรงกับ LeaveTypeID
                                        switch ($leave['LeaveTypeID']) {
                                            case 1:  // ลาป่วย
                                                $leave_data['ลาป่วย'] += $leave_days;
                                                break;
                                            case 2:  // ลากิจส่วนตัว
                                                $leave_data['ลากิจส่วนตัว'] += $leave_days;
                                                break;
                                            case 3:  // ลาพักผ่อน
                                                $leave_data['ลาพักผ่อน'] += $leave_days;
                                                break;
                                            case 4:  // ลาบวช
                                                $leave_data['ลาบวช'] += $leave_days;
                                                break;
                                            case 5:  // ลาไปถือศีล
                                                $leave_data['ลาไปถือศีล'] += $leave_days;
                                                break;
                                            case 6:  // ลาเข้ารับการตรวจเลือก
                                                $leave_data['ลาเข้ารับการตรวจเลือก'] += $leave_days;
                                                break;
                                            default:
                                                break;
                                        }
                                    }

                                    foreach ($leave_data as $leave_type => $days) {
                                        // คำนวณสถานะการได้รับค่าจ้าง
                                        $status = calculateStatus($leave_type, $days);

                                        // ตรวจสอบสถานะหลังจากคำนวณ
                                        if ($status == 'ไม่ได้รับค่าจ้าง') {
                                            break;  // หยุดการคำนวณหากพบว่าไม่เกินขีดจำกัดแล้ว
                                        }
                                    }

                                    ?>
                                    <span><?= "ลาป่วย: " . $leave_data['ลาป่วย'] . " วัน"; ?></span><br>
                                    <span><?= "ลากิจส่วนตัว: " . $leave_data['ลากิจส่วนตัว'] . " วัน"; ?></span><br>
                                    <span><?= "ลาพักผ่อน: " . $leave_data['ลาพักผ่อน'] . " วัน"; ?></span><br>
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
                                                    <h5 class="modal-title" id="remarkModalLabel<?= $user['UserID']; ?>">
                                                        รายละเอียดการลา</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <h6>ชื่อ:
                                                        <?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?>
                                                    </h6>
                                                    <p>ตำแหน่ง: <?= htmlspecialchars($user['Position']); ?></p>

                                                    <!-- Leave Type Pie Chart -->
                                                    <canvas id="leaveChart<?= $user['UserID']; ?>" width="200"
                                                        height="200"></canvas>
                                                    <script>
                                                        var leaveData = {
                                                            sickLeave: <?= $leave_data['ลาป่วย']; ?>,
                                                            personalLeave: <?= $leave_data['ลากิจส่วนตัว']; ?>,
                                                            vacationLeave: <?= $leave_data['ลาพักผ่อน']; ?>,
                                                            ordinationLeave: <?= $leave_data['ลาบวช']; ?>,
                                                            meditationLeave: <?= $leave_data['ลาไปถือศีล']; ?>,
                                                            militaryLeave: <?= $leave_data['ลาเข้ารับการตรวจเลือก']; ?>
                                                        };

                                                        // ฟังก์ชั่นสำหรับการแสดงกราฟ
                                                        var ctx = document.getElementById('leaveChart<?= $user['UserID']; ?>').getContext('2d');
                                                        var chart = new Chart(ctx, {
                                                            type: 'pie',
                                                            data: {
                                                                labels: ['ลาป่วย', 'ลากิจส่วนตัว', 'ลาพักผ่อน', 'ลาบวช', 'ลาไปถือศีล', 'ลาเข้ารับการตรวจเลือก'],
                                                                datasets: [{
                                                                    label: 'ประเภทการลา',
                                                                    data: [
                                                                        leaveData.sickLeave, leaveData.personalLeave, leaveData.vacationLeave, leaveData.ordinationLeave,
                                                                        leaveData.meditationLeave, leaveData.militaryLeave
                                                                    ],
                                                                    backgroundColor: ['#FF5733', '#33FF57', '#3357FF', '#FF8C00', '#FFD700', '#8A2BE2'],
                                                                    hoverOffset: 4
                                                                }]
                                                            },
                                                            options: {
                                                                responsive: true,
                                                                plugins: {
                                                                    legend: { position: 'top' }
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
                                                                    <td><?= htmlspecialchars($leave['leave_days']); ?> วัน</td>
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