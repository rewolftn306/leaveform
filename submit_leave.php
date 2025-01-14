<?php
// submit_leave.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php?error=กรุณาเข้าสู่ระบบก่อน");
    exit();
}

// ตรวจสอบว่าผู้ใช้มีบทบาทเป็น Employee หรือไม่
if ($_SESSION['Role'] !== 'Employee') {
    die("คุณไม่มีสิทธิ์ส่งคำขอการลา");
}

include('connect.php');

// ฟังก์ชั่นสำหรับการกรองข้อมูล
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    // รับและกรองข้อมูลจากฟอร์ม
    $leaveTypeID = intval($_POST['leave_type']);
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $remarks = sanitize_input($_POST['remarks']);

    // รับ EmployeeID จาก Session
    $employeeID = intval($_SESSION['UserID']);

    // ตรวจสอบว่า EmployeeID มีอยู่ในตาราง employees หรือไม่
    $stmt_check = $conn->prepare("SELECT EmployeeID FROM employees WHERE EmployeeID = ?");
    $stmt_check->bind_param("i", $employeeID);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows === 0) {
        $stmt_check->close();
        header("Location: inputform.php?error=ไม่มีข้อมูลพนักงานที่เกี่ยวข้องกับบัญชีนี้");
        exit();
    }
    $stmt_check->close();

    // ตรวจสอบวันที่
    if (empty($startDate) || empty($endDate)) {
        header("Location: inputform.php?error=กรุณากรอกวันที่เริ่มและสิ้นสุดการลา");
        exit();
    }

    if ($startDate > $endDate) {
        header("Location: inputform.php?error=วันที่เริ่มต้องไม่มากกว่าวันที่สิ้นสุด");
        exit();
    }

    // ตรวจสอบว่าประเภทการลาเป็นประเภทที่มีอยู่จริงหรือไม่
    $stmt = $conn->prepare("SELECT LeaveTypeID FROM leavetypes WHERE LeaveTypeID = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        header("Location: inputform.php?error=เกิดข้อผิดพลาดในการส่งคำขอการลา");
        exit();
    }
    $stmt->bind_param("i", $leaveTypeID);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        header("Location: inputform.php?error=ประเภทการลาไม่ถูกต้อง");
        exit();
    }
    $stmt->close();

    // ดึงเงื่อนไขการลาตามประเภทการลา
    $stmt_cond = $conn->prepare("SELECT ConditionDescription FROM leaveconditions WHERE LeaveTypeID = ?");
    $stmt_cond->bind_param("i", $leaveTypeID);
    $stmt_cond->execute();
    $result_cond = $stmt_cond->get_result();
    $conditions = [];
    if ($result_cond) {
        while ($row = $result_cond->fetch_assoc()) {
            $conditions[] = $row['ConditionDescription'];
        }
    }
    $stmt_cond->close();

    // ตัวอย่างการตรวจสอบเงื่อนไขการลา (ปรับให้เหมาะสม)
    foreach ($conditions as $condition) {
        // ตรวจสอบการไม่เกินจำนวนวันลา
        if (strpos($condition, 'ไม่เกิน') !== false) {
            preg_match('/ไม่เกิน (\d+) วัน/', $condition, $matches);
            if (isset($matches[1])) {
                $max_days = intval($matches[1]);
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                $interval = $start->diff($end);
                $days = $interval->days + 1; // รวมวันเริ่มต้น
                if ($days > $max_days) {
                    header("Location: inputform.php?error=จำนวนวันลามากกว่าที่กำหนดสำหรับประเภทการลานี้");
                    exit();
                }
            }
        }

        // คุณสามารถเพิ่มการตรวจสอบเงื่อนไขอื่นๆ ตามที่ต้องการได้ที่นี่
    }

    // แทรกข้อมูลการลา
    $sql = "INSERT INTO leaveapplications (EmployeeID, LeaveTypeID, StartDate, EndDate, ApprovalStatus, Remarks) VALUES (?, ?, ?, ?, 'Pending', ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        header("Location: inputform.php?error=เกิดข้อผิดพลาดในการส่งคำขอการลา");
        exit();
    }
    $stmt->bind_param("iisss", $employeeID, $leaveTypeID, $startDate, $endDate, $remarks);

    if ($stmt->execute()) {
        header("Location: inputform.php?success=ส่งคำขอการลาเรียบร้อยแล้ว");
        exit();
    } else {
        error_log("Execute failed: " . $stmt->error);
        header("Location: inputform.php?error=เกิดข้อผิดพลาดในการส่งคำขอการลา");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
