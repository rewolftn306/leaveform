<?php
// เชื่อมต่อฐานข้อมูล
include('connect.php');

// ตรวจสอบว่าได้รับ application_id หรือไม่
if (!isset($_GET['id'])) {
    die('ไม่พบข้อมูลใบลา');
}
$applicationId = $_GET['id'];

// ดึงข้อมูล PDF จากฐานข้อมูล
$stmt = $conn->prepare("SELECT PDFFile FROM leaveapplications WHERE ApplicationID = ?");
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$stmt->store_result();

// ตรวจสอบว่าเจอข้อมูลหรือไม่
if ($stmt->num_rows > 0) {
    $stmt->bind_result($pdfData);
    $stmt->fetch();

    // กำหนด headers สำหรับการแสดง PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="leave_application_' . $applicationId . '.pdf"');
    echo $pdfData; // แสดง PDF จากฐานข้อมูล
} else {
    die('ไม่พบไฟล์ PDF');
}

$stmt->close();
$conn->close();
?>
