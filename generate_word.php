<?php
require 'vendor/autoload.php'; // โหลด PHPWord
include('connect.php'); // เชื่อมต่อฐานข้อมูล

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

if (!isset($_GET['id'])) {
    die("ไม่พบรหัสใบลา");
}

$application_id = intval($_GET['id']); // ป้องกัน SQL Injection

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ดึงข้อมูลใบลาจาก database
$sql = "SELECT u.FirstName, u.LastName, lt.LeaveName, la.StartDate, la.EndDate, la.Remarks 
        FROM leaveapplications la
        JOIN users u ON la.EmployeeID = u.UserID
        JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        WHERE la.ApplicationID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("ไม่พบข้อมูลใบลา");
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close(); // ปิดการเชื่อมต่อฐานข้อมูล

// สร้างเอกสาร Word
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// ใส่ข้อมูลลงในไฟล์ Word
$section->addText("ใบลางาน", ['bold' => true, 'size' => 16], ['alignment' => 'center']);
$section->addTextBreak(1);
$section->addText("ชื่อ: " . $row['FirstName'] . " " . $row['LastName']);
$section->addText("ประเภทการลา: " . $row['LeaveName']);
$section->addText("วันที่ลา: " . $row['StartDate'] . " ถึง " . $row['EndDate']);
$section->addText("หมายเหตุ: " . ($row['Remarks'] ?: "ไม่มี"));

// ตรวจสอบว่าโฟลเดอร์ documents มีอยู่หรือไม่ ถ้าไม่มีให้สร้าง
$directory = "documents/";
if (!is_dir($directory)) {
    mkdir($directory, 0777, true);
}

// ตั้งชื่อไฟล์
$file = "leave_document_" . $application_id . ".docx";
$path = $directory . $file;

// บันทึกไฟล์ Word
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($path);

// ส่งไฟล์ให้ดาวน์โหลด
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"$file\"");
header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Length: " . filesize($path));
header("Cache-Control: must-revalidate");
header("Pragma: public");

ob_clean(); // ล้าง output buffer ป้องกัน error
flush();
readfile($path);
unlink($path); // ลบไฟล์หลังดาวน์โหลด
exit;
?>
