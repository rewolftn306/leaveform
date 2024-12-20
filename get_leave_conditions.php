<?php
// get_leave_conditions.php
include('connect.php');

header('Content-Type: application/json');

if (isset($_GET['leaveTypeID'])) {
    $leaveTypeID = intval($_GET['leaveTypeID']);
    $stmt = $conn->prepare("SELECT ConditionDescription FROM leaveconditions WHERE LeaveTypeID = ?");
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
