<?php
// approve_leave.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

include('connect.php');

// สร้าง CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$search_query = '';
$params = [];
$types = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_term'])) {
    $search_term = '%' . $_POST['search_term'] . '%';
    $search_query = "WHERE e.Name LIKE ?";
    $params[] = $search_term;
    $types .= 's';
}

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
        JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        $search_query
        ORDER BY la.ApplicationID DESC";

$stmt = $conn->prepare($sql);
if ($search_query) {
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

// ตรวจสอบการอนุมัติคำขอ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_leave'])) {
    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    $leave_id = intval($_POST['leave_id']);
    $status = 'Approved';

    $update_sql = "UPDATE leaveapplications SET ApprovalStatus = ? WHERE ApplicationID = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('si', $status, $leave_id);

    if ($update_stmt->execute()) {
        header("Location: approve_leave.php?success=1");
        exit;
    } else {
        echo "การอนุมัติลาล้มเหลว: " . htmlspecialchars($update_stmt->error);
    }

    $update_stmt->close();
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
    <style>
        body {
            background-color: #f4f4f9;
            padding-top: 70px;
        }
        .header {
            background-color: #343a40;
            color: white;
            padding: 15px;
        }
        .header .logo {
            font-size: 24px;
            font-weight: bold;
        }
        .header .user-info span {
            margin-right: 10px;
        }
    </style>
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
    <div class="container">

        <!-- Search Form -->
        <form method="POST" class="mt-4">
            <div class="input-group mb-3">
                <input type="text" name="search_term" class="form-control" placeholder="ค้นหาข้อมูล..." value="<?= isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>">
                <button class="btn btn-primary" type="submit">ค้นหา</button>
            </div>
        </form>

        <!-- Success Message -->
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                อนุมัติการลาสำเร็จ!
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
                                                echo '<span class="badge bg-secondary">ไม่ทราบ</span>';
                                        }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($leave['Remarks']); ?></td>
                                <td>
                                    <?php if ($leave['ApprovalStatus'] === 'Pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="leave_id" value="<?= intval($leave['ApplicationID']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                            <button type="submit" name="approve_leave" class="btn btn-sm btn-success" onclick="return confirm('คุณต้องการอนุมัติการลานี้หรือไม่?');">อนุมัติ</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>ไม่สามารถดำเนินการ</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>แสดงผลจากข้อมูล <?= count($leaves); ?> รายการ</div>
            <button class="btn btn-secondary" disabled>โหลดเพิ่มเติม</button>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
