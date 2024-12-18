<?php
// submit_leave.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php?error=กรุณาเข้าสู่ระบบก่อน");
    exit();
}

// รวมไฟล์เชื่อมต่อฐานข้อมูล
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
