<?php
// ตรวจสอบการเชื่อมต่อฐานข้อมูล
include('connect.php');
session_start();

if (!isset($_SESSION['Username'])) {
    echo json_encode([]); // ถ้าไม่ได้เข้าสู่ระบบ ส่งค่ากลับเป็น array ว่าง
    exit;
}

$user_name = $_SESSION['Username'];
$sql = "SELECT la.ApplicationID, la.ApprovalStatus, lt.LeaveName AS leave_type 
        FROM leaveapplications la
        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        WHERE la.UserID = (SELECT UserID FROM users WHERE username = ?) 
        ORDER BY la.CreateDate DESC LIMIT 1";  // ดึงข้อมูลการลาล่าสุด

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_name);
$stmt->execute();
$result = $stmt->get_result();

$leaveStatus = [];
if ($row = $result->fetch_assoc()) {
    $leaveStatus[] = [
        'application_id' => $row['ApplicationID'],
        'status' => $row['ApprovalStatus'],
        'leave_type' => $row['leave_type']
    ];
}

echo json_encode($leaveStatus);

$conn->close();
?>
