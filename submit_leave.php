<?php
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
function sanitize_input($data)
{
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
    $documentOption = isset($_POST['document_option']) ? $_POST['document_option'] : null;  // ตัวเลือกการแนบไฟล์หรือขอจัดส่ง

    // รับ UserID จาก Session
    $userID = intval($_SESSION['UserID']);

    // ตรวจสอบว่า UserID มีอยู่ในตาราง users หรือไม่
    $stmt_check = $conn->prepare("SELECT UserID FROM users WHERE UserID = ?");
    $stmt_check->bind_param("i", $userID);
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
    $stmt = $conn->prepare("SELECT LeaveTypeID, LeaveName FROM leavetypes WHERE LeaveTypeID = ?");
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

    // ดึงชื่อประเภทการลา
    $stmt->bind_result($leaveTypeID, $leaveTypeName);
    $stmt->fetch();
    $stmt->close();

    // ตรวจสอบว่ามีการเลือกตัวเลือก "แนบไฟล์" หรือ "ขอจัดส่ง"
    $documentFilePath = null;
    if ($documentOption === 'attach' && isset($_FILES['documents']) && $_FILES['documents']['error'] === UPLOAD_ERR_OK) {
        // อัปโหลดไฟล์ PDF และเก็บในโฟลเดอร์
        $uploadDir = 'uploads/';
        $filePath = $uploadDir . basename($_FILES['documents']['name']);
        if (move_uploaded_file($_FILES['documents']['tmp_name'], $filePath)) {
            $documentFilePath = $filePath;  // เก็บเส้นทางไฟล์
        } else {
            header("Location: inputform.php?error=ไม่สามารถอัปโหลดไฟล์ได้");
            exit();
        }
    }

    // แทรกข้อมูลการลา
    $sql = "INSERT INTO leaveapplications (UserID, LeaveTypeID, StartDate, EndDate, ApprovalStatus, Remarks, DocumentOption, PDFFile) VALUES (?, ?, ?, ?, 'Pending', ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        header("Location: inputform.php?error=เกิดข้อผิดพลาดในการส่งคำขอการลา");
        exit();
    }

    $stmt->bind_param("iisssss", $userID, $leaveTypeID, $startDate, $endDate, $remarks, $documentOption, $documentFilePath);

    if ($stmt->execute()) {
        // หลังจากบันทึกข้อมูลเสร็จ จะส่งไปที่หน้า generate_pdf.php พร้อมกับ application_id
        $application_id = $stmt->insert_id; // รับค่า application_id ที่บันทึก
        header("Location: generate_pdf.php?id=" . $application_id); // ส่งผู้ใช้ไปที่หน้า generate_pdf.php
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
