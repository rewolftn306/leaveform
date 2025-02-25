<?php
// get_leave_conditions.php
session_start();
include('connect.php');

header('Content-Type: application/json');

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if (isset($_GET['leaveTypeID'])) {
    $leaveTypeID = intval($_GET['leaveTypeID']);
    $stmt = $conn->prepare("SELECT ConditionDescription FROM leaveconditions WHERE LeaveTypeID = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
        exit;
    }
    $stmt->bind_param("i", $leaveTypeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $conditions = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $conditions[] = $row['ConditionDescription'];
        }
    }
    echo json_encode($conditions);
    $stmt->close();
} else {
    echo json_encode([]);
}

$conn->close();
?>