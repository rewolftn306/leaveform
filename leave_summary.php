<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'connect.php'; // เชื่อมต่อฐานข้อมูล

if (!isset($_SESSION['UserID'])) {
    die("กรุณาเข้าสู่ระบบก่อนเข้าถึงข้อมูล");
}

$userID = $_SESSION['UserID']; // ดึง ID พนักงานจาก session

// คำสั่ง SQL เพื่อดึงข้อมูลการลา
$sql = "SELECT 
    u.UserID, 
    u.CreatedAt, 
    TIMESTAMPDIFF(YEAR, u.CreatedAt, CURDATE()) AS WorkYears,
    lt.LeaveName,
    lc.TSMONEYTO AS MaxLeaveDays,
    IFNULL(SUM(DATEDIFF(la.EndDate, la.StartDate) + 1), 0) AS UsedLeaveDays,
    (lc.TSMONEYTO - IFNULL(SUM(DATEDIFF(la.EndDate, la.StartDate) + 1), 0)) AS RemainingLeaveDays
FROM users u
LEFT JOIN leaveapplications la ON u.UserID = la.UserID
LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
LEFT JOIN leaveconditions lc ON lt.LeaveTypeID = lc.LeaveTypeID
WHERE u.UserID = ?  -- ระบุ UserID ของพนักงาน
GROUP BY u.UserID, lt.LeaveName, lc.TSMONEYTO";

// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);

// ตรวจสอบว่าเตรียมคำสั่ง SQL สำเร็จหรือไม่
if ($stmt === false) {
    die('เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error);
}

// ผูกพารามิเตอร์
$stmt->bind_param("i", $userID); // bind parameter ให้กับ UserID (i = integer)
$stmt->execute(); // รันคำสั่ง SQL
$result = $stmt->get_result(); // ดึงผลลัพธ์
?>

<table border="1">
    <tr>
        <th>ประเภทการลา</th>
        <th>จำนวนวันลาทั้งหมด</th>
        <th>จำนวนวันลาที่ใช้ไป</th>
        <th>จำนวนวันลาที่เหลือ</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
    <tr>
        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
        <td><?php echo htmlspecialchars($row['MaxLeaveDays']); ?></td>
        <td><?php echo htmlspecialchars($row['UsedLeaveDays']); ?></td>
        <td><?php echo max(0, htmlspecialchars($row['RemainingLeaveDays'])); ?></td>
    </tr>
    <?php } ?>
</table>

<?php
$stmt->close(); // ปิด statement
$conn->close(); // ปิดการเชื่อมต่อฐานข้อมูล
?>
