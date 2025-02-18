<?php
// ตั้งค่าหน้าเว็บให้รองรับ UTF-8
header('Content-Type: text/html; charset=utf-8');

// โหลด autoload ของ Composer สำหรับ FPDI/FPDF
require_once('vendor/autoload.php');

use setasign\Fpdi\Fpdi;

// ตรวจสอบ parameter ที่ส่งมา
if (!isset($_GET['id'])) {
    die('ไม่พบข้อมูลใบลา');
}
$applicationId = $_GET['id'];

// เชื่อมต่อฐานข้อมูลและดึงข้อมูลใบลา
include('connect.php');
mysqli_set_charset($conn, "utf8"); // กำหนดให้รองรับ UTF-8

$stmt = $conn->prepare("SELECT la.*, u.FirstName, u.LastName, IFNULL(lt.LeaveName, 'ไม่ระบุ') as leave_type 
                        FROM leaveapplications la 
                        JOIN users u ON la.EmployeeID = u.UserID 
                        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID 
                        WHERE la.ApplicationID = ?");
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die('ไม่พบข้อมูลใบลา');
}
$leaveData = $result->fetch_assoc();
$stmt->close();
$conn->close();

// ดึงประเภทการลาเพื่อตัดสินใจเลือก template PDF
$leaveType = $leaveData['leave_type'];

// กำหนด mapping ระหว่างประเภทการลาและไฟล์แบบฟอร์ม PDF
switch ($leaveType) {
    case 'ลาป่วย':
    case 'ลากิจส่วนตัว':
    case 'การลาคลอดบุตร':
        $templatePath = 'form/Form-ใบลาป่วย-ลากิจส่วนตัว-ลาคลอดบุตร_2568-2.pdf';
        break;
    case 'ลาพักผ่อน':
        $templatePath = 'form/Form-ใบลาพักผ่อน_2568-2.pdf';
        break;
    case 'ขอยกเลิกวันลา':
        $templatePath = 'form/Form-ใบขอยกเลิกวันลา2568.pdf';
        break;
    case 'ลาอุปสมบท':
        $templatePath = 'form/ใบลาอุปสมบท.pdf';
        break;
    case 'ลาพักผ่อนไปต่างประเทศ':
        $templatePath = 'form/Form-ใบลาพักผ่อนไปต่างประเทศ.pdf';
        break;
    case 'ดูแลบุตรและภรรยาหลังคลอด':
        $templatePath = 'form/ใบลาดูแลบุตรและภรรยาหลังคลอด.pdf';
        break;
    default:
        $templatePath = 'form/default_template.pdf';
        break;
}

// ตรวจสอบว่าไฟล์ template มีอยู่หรือไม่
if (!file_exists($templatePath)) {
    die("ไม่พบไฟล์ template: " . $templatePath);
}

// สร้าง PDF โดยใช้ FPDI
$pdf = new Fpdi();
$pdf->AddPage();

// โหลดฟอนต์ภาษาไทย THSarabunNew
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->SetFont('THSarabunNew', '', 16);


// โหลด Template PDF
try {
    $pageCount = $pdf->setSourceFile($templatePath);
    $tplIdx = $pdf->importPage(1);
    $pdf->useTemplate($tplIdx);
} catch (Exception $e) {
    die("ไม่สามารถโหลดไฟล์ PDF: " . $e->getMessage());
}

// กำหนดฟอนต์และสีของข้อความ
$pdf->SetTextColor(0, 0, 0);

// ฟังก์ชันสำหรับแก้ปัญหา Encoding ภาษาไทย
function convertThai($text) {
    return iconv("UTF-8", "Windows-874//IGNORE", $text);
}


// แทรกข้อมูลลงในตำแหน่งที่กำหนด
$pdf->SetXY(56, 56.5);
$pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));

$pdf->SetXY(47, 88);
$pdf->Write(0, convertThai('' . $leaveData['StartDate']));

$pdf->SetXY(107, 88);
$pdf->Write(0, convertThai('' . $leaveData['EndDate']));

$pdf->SetXY(50, 80);
$pdf->Write(0, convertThai('ประเภทการลา: ' . $leaveType));

$pdf->SetXY(50, 91);
$pdf->Write(0, convertThai('หมายเหตุ: ' . ($leaveData['Remarks'] ? $leaveData['Remarks'] : 'ไม่มี')));

// ส่งออก PDF ไปยังเบราว์เซอร์
$pdf->Output('I', 'ใบลา_' . $applicationId . '.pdf');
?>
