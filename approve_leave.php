<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบบทบาทของผู้ใช้
$allowed_roles = ['Director', 'Leader', 'Leader2', 'Leader3', 'Admin'];
if (!in_array($_SESSION['Role'], $allowed_roles)) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

include('connect.php');
include('notification.php');

// สร้าง CSRF Token หากยังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ตรวจสอบการอนุมัติหรือปฏิเสธคำขอ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $leave_id = intval($_POST['leave_id']);
    $current_role = $_SESSION['Role']; // บทบาทของผู้ใช้งานจาก session

    // สมมติว่าเราจะส่งข้อความหาผู้ใช้งานในระดับถัดไป
    if (isset($_POST['approve_leave'])) {
        // เรียกใช้ไฟล์ที่อัปเดต PDF ด้วยลายเซ็น
        $leave_id = intval($_POST['leave_id']);
        include('process_leave_approval.php'); // ไฟล์นี้จะทำการอัปเดต PDF และเพิ่มลายเซ็น

        $message = "คำขอลานี้ได้รับการอนุมัติจาก " . $current_role . " แล้ว กรุณากดอนุมัติหรือปฏิเสธคำขอของคุณ";
        sendGoogleChatNotification($message, $current_role);  // ส่งข้อความตามบทบาทที่เลือก
    }


    $leave_id = intval($_POST['leave_id']);
    $current_role = $_SESSION['Role'];

    // ตรวจสอบว่าคำขอนี้ยังคงอยู่ในสถานะ Pending
    $stmt_check = $conn->prepare("SELECT ApprovalStatus, LeaderApprovalStatus, Leader2ApprovalStatus, Leader3ApprovalStatus, DirectorApprovalStatus FROM leaveapplications WHERE ApplicationID = ?");
    $stmt_check->bind_param('i', $leave_id);
    $stmt_check->execute();
    $stmt_check->bind_result($current_status, $leader_status, $leader2_status, $leader3_status, $director_status);
    $stmt_check->fetch();
    $stmt_check->close();

    // หากสถานะการลาไม่ใช่ Pending
    if ($current_status !== 'Pending') {
        header("Location: approve_leave.php?error=คำขอนี้ได้ถูกดำเนินการแล้ว");
        exit;
    }

    // ตรวจสอบสถานะการอนุมัติแต่ละระดับ
    if (isset($_POST['approve_leave'])) {
        if ($current_role === 'Leader' && $leader_status === 'Pending') {
            $update_sql = "UPDATE leaveapplications SET LeaderApprovalStatus = 'Approved' WHERE ApplicationID = ?";
        } elseif ($current_role === 'Leader2' && $leader2_status === 'Pending') {
            $update_sql = "UPDATE leaveapplications SET Leader2ApprovalStatus = 'Approved' WHERE ApplicationID = ?";
        } elseif ($current_role === 'Leader3' && $leader3_status === 'Pending') {
            $update_sql = "UPDATE leaveapplications SET Leader3ApprovalStatus = 'Approved' WHERE ApplicationID = ?";
        } elseif ($current_role === 'Director' && $director_status === 'Pending') {
            $update_sql = "UPDATE leaveapplications SET DirectorApprovalStatus = 'Approved', ApprovalStatus = 'Approved' WHERE ApplicationID = ?";
        } else {
            exit;
        }
    } elseif (isset($_POST['reject_leave'])) {
        $update_sql = "UPDATE leaveapplications SET LeaderApprovalStatus = 'Rejected', Leader2ApprovalStatus = 'Rejected', Leader3ApprovalStatus = 'Rejected', DirectorApprovalStatus = 'Rejected', ApprovalStatus = 'Rejected' WHERE ApplicationID = ?";
    }

    // หากมีการอัปเดตสถานะ
    if (isset($update_sql)) {
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('i', $leave_id);

        if ($update_stmt->execute()) {
            header("Location: approve_leave.php?success=1");
            exit;
        } else {
            echo "การอัปเดตสถานะการลาไม่สำเร็จ: " . htmlspecialchars($update_stmt->error);
        }

        $update_stmt->close();
    }
}

// ดึงคำขอลาการลาที่รอดำเนินการ
$search_term = '';
$status_filter = '';
$params = [];
$types = '';
$where_clauses = [];

$role = $_SESSION['Role'];  // กำหนดค่า role จาก session ของผู้ใช้

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

        if ($role === 'Leader') {
            $where_clauses[] = "la.LeaderApprovalStatus = ?";
        } elseif ($role === 'Leader2') {
            $where_clauses[] = "la.Leader2ApprovalStatus = ?";
        } elseif ($role === 'Leader3') {
            $where_clauses[] = "la.Leader3ApprovalStatus = ?";
        } elseif ($role === 'Director') {
            $where_clauses[] = "la.DirectorApprovalStatus = ?";
        } elseif ($role === 'Admin') {
            $where_clauses[] = "la.ApprovalStatus = ?";
        }

        $params[] = $status_filter;
        $types .= 's';
    }
}

$sql = "SELECT 
            la.ApplicationID, 
            la.UserID, 
            la.LeaveTypeID, 
            la.StartDate, 
            la.EndDate, 
            la.ApprovalStatus, 
            la.Remarks, 
            u.FirstName, 
            u.LastName, 
            lt.LeaveName,
            la.LeaderApprovalStatus, 
            la.Leader2ApprovalStatus, 
            la.Leader3ApprovalStatus, 
            la.DirectorApprovalStatus
        FROM leaveapplications la
        JOIN users u ON la.UserID = u.UserID
        JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY CASE WHEN la.ApprovalStatus = 'Pending' THEN 0 ELSE 1 END, la.ApplicationID DESC";

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
                        <input type="text" name="search_term" class="form-control" placeholder="ค้นหาชื่อพนักงาน..."
                            value="<?= isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="approval_status" class="form-select">
                        <option value="">-- เลือกสถานะการอนุมัติ --</option>
                        <option value="Pending" <?= (isset($_GET['approval_status']) && $_GET['approval_status'] === 'Pending') ? 'selected' : ''; ?>>รอดำเนินการ</option>
                        <option value="Approved" <?= (isset($_GET['approval_status']) && $_GET['approval_status'] === 'Approved') ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                        <option value="Rejected" <?= (isset($_GET['approval_status']) && $_GET['approval_status'] === 'Rejected') ? 'selected' : ''; ?>>ถูกปฏิเสธแล้ว</option>
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
                                <td><?= htmlspecialchars($leave['FirstName']); ?></td>
                                <td><?= htmlspecialchars($leave['LeaveName']); ?></td>
                                <td><?= htmlspecialchars($leave['StartDate']); ?></td>
                                <td><?= htmlspecialchars($leave['EndDate']); ?></td>
                                <td>
                                    <?php
                                    switch ($_SESSION['Role']) {
                                        case 'Leader':
                                            echo $leave['LeaderApprovalStatus'] === 'Approved' ? '<span class="badge bg-success">อนุมัติแล้ว</span>' :
                                                ($leave['LeaderApprovalStatus'] === 'Rejected' ? '<span class="badge bg-danger">ถูกปฏิเสธแล้ว</span>' :
                                                    '<span class="badge bg-warning text-dark">รอดำเนินการ</span>');
                                            break;
                                        case 'Leader2':
                                            echo $leave['Leader2ApprovalStatus'] === 'Approved' ? '<span class="badge bg-success">อนุมัติแล้ว</span>' :
                                                ($leave['Leader2ApprovalStatus'] === 'Rejected' ? '<span class="badge bg-danger">ถูกปฏิเสธแล้ว</span>' :
                                                    '<span class="badge bg-warning text-dark">รอดำเนินการ</span>');
                                            break;
                                        case 'Leader3':
                                            echo $leave['Leader3ApprovalStatus'] === 'Approved' ? '<span class="badge bg-success">อนุมัติแล้ว</span>' :
                                                ($leave['Leader3ApprovalStatus'] === 'Rejected' ? '<span class="badge bg-danger">ถูกปฏิเสธแล้ว</span>' :
                                                    '<span class="badge bg-warning text-dark">รอดำเนินการ</span>');
                                            break;
                                        case 'Director':
                                            echo $leave['DirectorApprovalStatus'] === 'Approved' ? '<span class="badge bg-success">อนุมัติแล้ว</span>' :
                                                ($leave['DirectorApprovalStatus'] === 'Rejected' ? '<span class="badge bg-danger">ถูกปฏิเสธแล้ว</span>' :
                                                    '<span class="badge bg-warning text-dark">รอดำเนินการ</span>');
                                            break;
                                        case 'Admin':
                                            echo $leave['LeaderApprovalStatus'] === 'Approved' ? '<span class="badge bg-success">อนุมัติแล้ว</span>' :
                                                ($leave['LeaderApprovalStatus'] === 'Rejected' ? '<span class="badge bg-danger">ถูกปฏิเสธแล้ว</span>' :
                                                    '<span class="badge bg-warning text-dark">รอดำเนินการ</span>');
                                            break;
                                        default:
                                            echo '<span class="badge bg-secondary">สถานะไม่รู้จัก</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <!-- ปุ่มพิมพ์ PDF -->
                                    <a href="view_pdf.php?id=<?= $leave['ApplicationID']; ?>"
                                        class="btn btn-primary btn-sm">ดูเอกสาร</a>
                                    <!-- ปุ่มดูรายละเอียด -->
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#remarkModal<?= $leave['ApplicationID']; ?>">
                                        รายละเอียดการลา
                                    </button>

                                    <!-- Modal สำหรับการแสดงหมายเหตุ -->
                                    <div class="modal fade" id="remarkModal<?= $leave['ApplicationID']; ?>" tabindex="-1"
                                        aria-labelledby="remarkModalLabel<?= $leave['ApplicationID']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"
                                                        id="remarkModalLabel<?= $leave['ApplicationID']; ?>">รายละเอียดหมายเหตุ
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <?= nl2br(htmlspecialchars($leave['Remarks'])); ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">ปิด</button>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    switch ($_SESSION['Role']) {
                                        case 'Leader':
                                            if ($leave['LeaderApprovalStatus'] === 'Pending') {
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
                                            }
                                            break;
                                        case 'Leader2':
                                            // ตรวจสอบว่า LeaderApprovalStatus ยังเป็น Pending หรือไม่
                                            if ($leave['LeaderApprovalStatus'] === 'Pending') {
                                                // ถ้ายังเป็น Pending ให้แสดงข้อความว่า รอการอนุมัติจาก Leader
                                                echo '<div class="text-muted">รอการอนุมัติจาก Leader</div>';
                                            } elseif ($leave['Leader2ApprovalStatus'] === 'Pending') {
                                                // ถ้าหากสถานะของ Leader2 ยังเป็น Pending ให้แสดงปุ่มอนุมัติและปฏิเสธ
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
                                            }
                                            break;
                                        case 'Leader3':
                                            // ตรวจสอบสถานะของ Leader และ Leader2 ว่ายังเป็น Pending หรือไม่
                                            if ($leave['LeaderApprovalStatus'] === 'Pending') {
                                                echo '<div class="text-muted">รอการอนุมัติจาก Leader</div>';
                                            } elseif ($leave['Leader2ApprovalStatus'] === 'Pending') {
                                                echo '<div class="text-muted">รอการอนุมัติจาก Leader2</div>';
                                            } elseif ($leave['Leader3ApprovalStatus'] === 'Pending') {
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
                                            }
                                            break;

                                        case 'Director':
                                            // ตรวจสอบสถานะของ Leader, Leader2, Leader3 ว่ายังเป็น Pending หรือไม่
                                            if ($leave['LeaderApprovalStatus'] === 'Pending') {
                                                echo '<div class="text-muted">รอการอนุมัติจาก Leader</div>';
                                            } elseif ($leave['Leader2ApprovalStatus'] === 'Pending') {
                                                echo '<div class="text-muted">รอการอนุมัติจาก Leader2</div>';
                                            } elseif ($leave['Leader3ApprovalStatus'] === 'Pending') {
                                                echo '<div class="text-muted">รอการอนุมัติจาก Leader3</div>';
                                            } elseif ($leave['DirectorApprovalStatus'] === 'Pending') {
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
                                            }
                                            break;

                                    }

                                    ?>
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
        <a href="index.php" class="btn btn-danger mt-3 w-20">ย้อนกลับ</a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>