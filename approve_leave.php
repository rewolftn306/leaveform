<?php
// approve_leave.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบบทบาทของผู้ใช้
$allowed_roles = ['Director', 'Admin'];
if (!in_array($_SESSION['Role'], $allowed_roles)) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

include('connect.php');

// สร้าง CSRF Token หากยังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ตรวจสอบการอนุมัติหรือปฏิเสธคำขอ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $leave_id = intval($_POST['leave_id']);

    // ตรวจสอบว่าคำขอนี้ยังคงอยู่ในสถานะ Pending
    $stmt_check = $conn->prepare("SELECT ApprovalStatus FROM leaveapplications WHERE ApplicationID = ?");
    $stmt_check->bind_param('i', $leave_id);
    $stmt_check->execute();
    $stmt_check->bind_result($current_status);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($current_status !== 'Pending') {
        header("Location: approve_leave.php?error=คำขอนี้ได้ถูกดำเนินการแล้ว");
        exit;
    }

    if (isset($_POST['approve_leave'])) {
        $status = 'Approved';
    } elseif (isset($_POST['reject_leave'])) {
        $status = 'Rejected';
    } else {
        // ไม่ทำอะไรถ้าไม่ใช่การอนุมัติหรือปฏิเสธ
        exit;
    }

    $update_sql = "UPDATE leaveapplications SET ApprovalStatus = ? WHERE ApplicationID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('si', $status, $leave_id);

    if ($update_stmt->execute()) {
        // ส่งอีเมล์แจ้งเตือนผู้ยื่นคำขอ
        $stmt_leave = $conn->prepare("SELECT la.*, u.Email, u.FirstName, u.LastName, lt.LeaveName FROM leaveapplications la JOIN users u ON la.EmployeeID = u.UserID JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID WHERE la.ApplicationID = ?");
        $stmt_leave->bind_param("i", $leave_id);
        $stmt_leave->execute();
        $result_leave = $stmt_leave->get_result();
        if ($result_leave->num_rows > 0) {
            $leave = $result_leave->fetch_assoc();
            $email = $leave['Email'];
            $firstName = $leave['FirstName'];
            $lastName = $leave['LastName'];
            $leaveType = $leave['LeaveName'];
            $startDate = $leave['StartDate'];
            $endDate = $leave['EndDate'];
            $remarks = $leave['Remarks'];
            $subject = "การอนุมัติการลาของคุณถูก $status";
            $message = "สวัสดีคุณ $firstName $lastName,\n\nคำขอลาการลาของคุณได้ถูก $status.\n\nรายละเอียด:\nประเภทการลา: $leaveType\nวันที่เริ่ม: $startDate\nวันที่สิ้นสุด: $endDate\nหมายเหตุ: $remarks\n\nขอบคุณ.";
            $headers = "From: no-reply@yourdomain.com\r\n" .
                       "Reply-To: no-reply@yourdomain.com\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // ส่งอีเมล์อย่างปลอดภัย
            if (!mail($email, $subject, $message, $headers)) {
                error_log("Failed to send email to $email for leave application ID $leave_id");
            }
        }
        $stmt_leave->close();

        header("Location: approve_leave.php?success=1");
        exit;
    } else {
        echo "การอัปเดตสถานะการลาไม่สำเร็จ: " . htmlspecialchars($update_stmt->error);
    }

    $update_stmt->close();
}

// ดึงคำขอลาการลาที่รอดำเนินการและตามการค้นหา
$search_term = '';
$status_filter = '';
$params = [];
$types = '';
$where_clauses = [];

// ใช้ GET สำหรับการค้นหาและกรอง
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // การค้นหาด้วยคำค้นหาตามชื่อ
    if (isset($_GET['search_term']) && !empty(trim($_GET['search_term']))) {
        $search_term = '%' . trim($_GET['search_term']) . '%';
        $where_clauses[] = "e.Name LIKE ?";
        $params[] = $search_term;
        $types .= 's';
    }

    // การกรองตามสถานะการอนุมัติ
    if (isset($_GET['approval_status']) && $_GET['approval_status'] !== '') {
        $status_filter = $_GET['approval_status'];
        $where_clauses[] = "la.ApprovalStatus = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
}

// สร้างคำสั่ง SQL พร้อมการค้นหาและกรอง
$sql = "SELECT 
            la.ApplicationID, 
            la.EmployeeID, 
            la.LeaveTypeID, 
            la.StartDate, 
            la.EndDate, 
            la.ApprovalStatus, 
            la.Remarks, 
            e.Name AS EmployeeName,
            lt.LeaveName
        FROM leaveapplications la
        JOIN employees e ON la.EmployeeID = e.EmployeeID
        JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY la.ApplicationID DESC";

// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $leaves = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $leaves[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Prepare failed: " . $conn->error);
    $leaves = [];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การอนุมัติการลา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <input type="text" name="search_term" class="form-control" placeholder="ค้นหาชื่อพนักงาน..." value="<?= isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
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
                    <a href="approve_leave.php" class="btn btn-secondary w-20">รีเซ็ต</a>
                </div>
            </div>
        </form>

        <!-- Success or Error Message -->
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                อนุมัติหรือปฏิเสธการลาสำเร็จ!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Leaves Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อ</th>
                        <th>ประเภทการลา</th>
                        <th>วันที่เริ่ม</th>
                        <th>วันที่สิ้นสุด</th>
                        <th>สถานะ</th>
                        <th>หมายเหตุ</th>
                        <th>การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr>
                            <td colspan="8" class="text-center">ไม่พบข้อมูล</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaves as $index => $leave): ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= htmlspecialchars($leave['EmployeeName']); ?></td>
                                <td><?= htmlspecialchars($leave['LeaveName']); ?></td>
                                <td><?= htmlspecialchars($leave['StartDate']); ?></td>
                                <td><?= htmlspecialchars($leave['EndDate']); ?></td>
                                <td>
                                    <?php
                                        switch ($leave['ApprovalStatus']) {
                                            case 'Approved':
                                                echo '<span class="badge bg-success">อนุมัติแล้ว</span>';
                                                break;
                                            case 'Pending':
                                                echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                                break;
                                            case 'Rejected':
                                                echo '<span class="badge bg-danger">ถูกปฏิเสธ</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">ยกเลิก</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <!-- ปุ่มดูรายละเอียด -->
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#remarkModal<?= $leave['ApplicationID']; ?>">
                                        ดูรายละเอียด
                                    </button>

                                    <!-- Modal สำหรับการแสดงหมายเหตุ -->
                                    <div class="modal fade" id="remarkModal<?= $leave['ApplicationID']; ?>" tabindex="-1" aria-labelledby="remarkModalLabel<?= $leave['ApplicationID']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="remarkModalLabel<?= $leave['ApplicationID']; ?>">รายละเอียดหมายเหตุ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <?= nl2br(htmlspecialchars($leave['Remarks'])); ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php switch ($leave['ApprovalStatus']) {
                                            case 'Approved':
                                                echo '<span class="badge bg-success">ดำเนินการแล้ว</span>';
                                                break;
                                            case 'Pending':
                                                echo '<form method="POST" class="d-inline">
                                                        <input type="hidden" name="leave_id" value="' . intval($leave['ApplicationID']) . '">
                                                        <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                                                        <button type="submit" name="approve_leave" class="btn btn-sm btn-success" onclick="return confirm(\'คุณต้องการอนุมัติการลานี้หรือไม่?\');">อนุมัติ</button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="leave_id" value="' . intval($leave['ApplicationID']) . '">
                                                        <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                                                        <button type="submit" name="reject_leave" class="btn btn-sm btn-danger" onclick="return confirm(\'คุณต้องการปฏิเสธการลานี้หรือไม่?\');">ปฏิเสธ</button>
                                                    </form>';
                                                break;
                                            case 'Rejected':
                                                echo '<span class="badge bg-danger">ดำเนินการแล้ว</span>';
                                                break;
                                            case 'Cancelled':
                                                echo '<span class="badge bg-secondary">ยกเลิกการดำเนินการ</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">สถานะไม่รู้จัก</span>';
                                        } ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination (ตัวอย่างการเพิ่มหน้า) -->
        <?php
            // เพิ่มการแบ่งหน้า (Pagination) ที่นี่ หากข้อมูลมีจำนวนมาก
            // ตัวอย่างเช่น การแบ่งหน้าเป็น 10 รายการต่อหน้า
            // คุณสามารถใช้ library เช่น [Pagination](https://getbootstrap.com/docs/5.3/components/pagination/)
        ?>

        <!-- Footer -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>แสดงผลจากข้อมูล <?= count($leaves); ?> รายการ</div>
            <button class="btn btn-secondary" disabled>โหลดเพิ่มเติม</button>
        </div>
        <a href="index.php" class="btn btn-danger mt-3 w-20">ย้อนกลับ</a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
