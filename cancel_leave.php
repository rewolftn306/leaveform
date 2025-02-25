<?php
// cancel_leave.php
session_start();
include('connect.php');

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (!isset($_SESSION['Username'])) {
    echo 'Unauthorized';
    exit;
}

$user_name = $_SESSION['Username'];
$application_id = $_POST['application_id'] ?? null;  // รับค่า ApplicationID จากคำขอ POST

if (!$application_id) {
    echo 'No Application ID provided';
    exit;
}

// ตรวจสอบว่า application_id เป็นตัวเลขหรือไม่ (ช่วยป้องกัน SQL Injection)
if (!is_numeric($application_id)) {
    echo 'Invalid Application ID';
    exit;
}

// ตรวจสอบว่า application_id ที่ส่งมาคือการลาเดียวกันกับผู้ใช้ที่ล็อกอินอยู่
$sql = "SELECT la.EmployeeID, la.ApprovalStatus 
        FROM leaveapplications la 
        JOIN users u ON la.EmployeeID = u.UserID
        WHERE la.ApplicationID = ? AND u.Username = ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    // ตรวจสอบว่า query ถูกเตรียมสำเร็จหรือไม่
    echo "Error preparing the statement: " . $conn->error;
    exit;
}

$stmt->bind_param("is", $application_id, $user_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    // ตรวจสอบสถานะการลา หากสถานะเป็น "Pending" ให้ยกเลิกได้
    if ($row['ApprovalStatus'] === 'Pending') {
        // เปลี่ยนสถานะการลาเป็น 'Cancelled'
        $update_sql = "UPDATE leaveapplications SET ApprovalStatus = 'Cancelled' WHERE ApplicationID = ?";
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt === false) {
            // ตรวจสอบว่า query ถูกเตรียมสำเร็จหรือไม่
            echo "Error preparing the update statement: " . $conn->error;
            exit;
        }

        $update_stmt->bind_param("i", $application_id);
        if ($update_stmt->execute()) {
            echo 'success';
        } else {
            echo 'Error updating the leave status: ' . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        echo 'The leave is not pending, cannot cancel.';
    }
} else {
    echo 'You are not authorized to cancel this leave or leave does not exist.';
}

$stmt->close();
$conn->close();
?>