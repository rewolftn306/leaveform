<?php
// get_leave_data.php
include('connect.php');

// กรองข้อมูลเฉพาะสถานะ "Approved"
$sql = "SELECT u.FirstName, u.LastName, IFNULL(lt.LeaveName, 'ไม่ระบุ') AS leave_type, 
        la.StartDate, la.EndDate, la.ApprovalStatus, la.Remarks, la.ApplicationID 
        FROM users u
        LEFT JOIN leaveapplications la ON u.UserID = la.UserID
        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        WHERE la.ApprovalStatus = 'Approved' AND la.LeaveTypeID IS NOT NULL
        ORDER BY la.CreateDate DESC";
        
$result = $conn->query($sql);
$chartData = [];
$leaveTypes = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $start_date = new DateTime($row['StartDate']);
        $end_date = new DateTime($row['EndDate']);
        $interval = $start_date->diff($end_date);
        $leave_days = $interval->days;

        // เพิ่มข้อมูลสำหรับกราฟ
        if (!in_array($row['leave_type'], $leaveTypes)) {
            $leaveTypes[] = $row['leave_type'];
        }

        if (!isset($chartData[$row['leave_type']])) {
            $chartData[$row['leave_type']] = 0;
        }
        $chartData[$row['leave_type']] += $leave_days;
    }
}

$conn->close();

// ส่งข้อมูลกราฟในรูปแบบ JSON
echo json_encode([
    'labels' => array_values($leaveTypes),
    'values' => array_values($chartData)
]);

?>