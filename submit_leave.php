<?php
// การเชื่อมต่อฐานข้อมูล
include('connect.php');

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// รับค่าจากฟอร์ม
$employeeID = $_POST['empid']; // รหัสพนักงาน
$leaveTypeID = $_POST['leave_type']; // ประเภทการลา
$startDate = $_POST['start_date']; // วันที่เริ่มลา
$endDate = $_POST['end_date']; // วันที่สิ้นสุดการลา
$remarks = $_POST['remarks']; // หมายเหตุ

// สร้างคำสั่ง SQL
$sql = "INSERT INTO leaveapplications (EmployeeID, LeaveTypeID, StartDate, EndDate, Remarks)
        VALUES (?, ?, ?, ?, ?)";

// เตรียมคำสั่ง SQL
$stmt = $conn->prepare($sql);
$stmt->bind_param("iisss", $employeeID, $leaveTypeID, $startDate, $endDate, $remarks);

// ดำเนินการบันทึกข้อมูล
if ($stmt->execute()) {
    echo "บันทึกข้อมูลการลาเรียบร้อยแล้ว <a href='inputform.php'>กรอกข้อมูลใหม่</a>";
} else {
    echo "เกิดข้อผิดพลาด: " . $stmt->error;
}

// ปิดการเชื่อมต่อ
$stmt->close();
$conn->close();
?>
