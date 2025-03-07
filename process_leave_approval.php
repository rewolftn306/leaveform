<?php
require_once('vendor/autoload.php');
use setasign\Fpdi\Fpdi;

// การเชื่อมต่อกับฐานข้อมูล
include('connect.php');

// รับ ID การขอลาจาก POST request
$leave_id = intval($_POST['leave_id']);
$current_role = $_SESSION['Role']; // บทบาทของผู้ใช้งานจาก session

// ดึงข้อมูล PDF จากฐานข้อมูล
$stmt = $conn->prepare("SELECT la.PDFFile, la.LeaveTypeID 
                        FROM leaveapplications la
                        WHERE la.ApplicationID = ?");
$stmt->bind_param("i", $leave_id);
$stmt->execute();
$stmt->bind_result($pdfFileBlob, $leaveTypeID);
$stmt->fetch();
$stmt->close();

// ตรวจสอบว่า PDF มีอยู่หรือไม่
if (!$pdfFileBlob) {
    die('ไม่พบไฟล์ PDF');
}

// ดึงลายเซ็นจากฐานข้อมูล
$stmt = $conn->prepare("SELECT Signature FROM users WHERE UserID = ?");
$stmt->bind_param("i", $_SESSION['UserID']); // ใช้ UserID จาก session
$stmt->execute();
$stmt->bind_result($signatureBlob);
$stmt->fetch();
$stmt->close();

// ตรวจสอบว่ามีลายเซ็นหรือไม่
if (!$signatureBlob) {
    die('ไม่พบลายเซ็นของผู้อนุมัติ');
}

// ตรวจสอบและแยก base64 ออกจาก MIME type
if (strpos($signatureBlob, 'data:image/') === 0) {
    // ตัดส่วน MIME type ออก
    $signatureBlob = substr($signatureBlob, strpos($signatureBlob, ",") + 1);
}

// ตรวจสอบว่า base64 ถูกต้องหรือไม่
if (base64_decode($signatureBlob, true) === false) {
    die("ข้อมูลลายเซ็นไม่ถูกต้อง");
}

// แปลงข้อมูล base64 เป็นข้อมูลภาพ
$signatureImageData = base64_decode($signatureBlob);

// สร้างไฟล์ชั่วคราวสำหรับลายเซ็น
$tempImageFile = tempnam(sys_get_temp_dir(), 'signature_') . '.png';  // ใช้ .png หรือ .jpg ตามประเภทของไฟล์ที่คุณใช้

// เขียนข้อมูล base64 ลงในไฟล์ภาพชั่วคราว
file_put_contents($tempImageFile, $signatureImageData);

// ตรวจสอบว่าไฟล์ถูกสร้างขึ้นหรือไม่
if (!file_exists($tempImageFile)) {
    die('ไม่สามารถสร้างไฟล์ลายเซ็นได้');
}

// ตรวจสอบว่าไฟล์ที่สร้างขึ้นสามารถเปิดได้เป็น PNG หรือไม่
$imageInfo = getimagesize($tempImageFile);
if (!$imageInfo || $imageInfo['mime'] !== 'image/png') {
    die('ไฟล์ที่สร้างขึ้นไม่ใช่ PNG หรือไม่สามารถอ่านได้');
}

// สร้างไฟล์ชั่วคราวสำหรับ PDF
$tempPdfFile = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
file_put_contents($tempPdfFile, $pdfFileBlob);

// สร้าง FPDI instance ใหม่
$pdf = new Fpdi();
$pdf->AddPage();

// โหลดไฟล์ PDF ที่มีอยู่จากไฟล์ชั่วคราว
$pdf->setSourceFile($tempPdfFile);
$tplIdx = $pdf->importPage(1);
$pdf->useTemplate($tplIdx);

// ตั้งค่าฟอนต์สำหรับลายเซ็น
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');

// กำหนดขนาดของลายเซ็น
$signatureWidth = 30;  // ความกว้าง
$signatureHeight = 10; // ความสูง

// กำหนดตำแหน่งเริ่มต้นของลายเซ็น
$signatureX = 100;  // ตำแหน่งเริ่มต้นของลายเซ็น
$signatureY = 123;  // ตำแหน่งเริ่มต้นของลายเซ็น

// ตรวจสอบ LeaveTypeID และปรับตำแหน่งลายเซ็น
if ($leaveTypeID == 1) {
    // ถ้า LeaveTypeID เป็น 1 ให้ปรับตำแหน่งใหม่
    $signatureX = 50;  // ปรับตำแหน่ง X
    $signatureY = 150; // ปรับตำแหน่ง Y
}

// ตรวจสอบ Role ของผู้ใช้งานและปรับตำแหน่งลายเซ็น
if ($current_role == 'Leader') {
    // ถ้าผู้ใช้งานเป็น Leader ปรับตำแหน่งลายเซ็น
    $signatureX = 70;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
    $signatureY = 200; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
} elseif ($current_role == 'Manager') {
    // ถ้าผู้ใช้งานเป็น Manager ปรับตำแหน่งลายเซ็น
    $signatureX = 100;
    $signatureY = 180;
}

// แทรกลายเซ็นในไฟล์ PDF
$pdf->Image($tempImageFile, $signatureX, $signatureY, $signatureWidth, $signatureHeight); // แทรกลายเซ็นที่ตำแหน่งที่กำหนด

// ลบไฟล์ชั่วคราวหลังจากใช้งานเสร็จ
unlink($tempPdfFile);
unlink($tempImageFile);

// ส่งออก PDF ที่แก้ไขแล้วเป็นข้อมูลไบนารี
$pdfData = $pdf->Output('S');

// อัปเดตฐานข้อมูลด้วย PDF ที่มีลายเซ็นใหม่
$stmt = $conn->prepare("UPDATE leaveapplications SET PDFFile = ? WHERE ApplicationID = ?");
$stmt->bind_param("bi", $null, $leave_id);
$stmt->send_long_data(0, $pdfData);
$stmt->execute();
$stmt->close();

// กำหนดชื่อไฟล์ PDF
$applicationId = $leave_id;  // กำหนดตัวแปร applicationId ให้ตรงกับ leave_id

echo "PDF ถูกอัปเดตเรียบร้อยแล้ว";

/*// รีไดเร็กต์หรือดำเนินการต่อกับการอนุมัติ
header("Location: approve_leave.php?success=1");
exit;*/
?>
