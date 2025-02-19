<?php
// Set up UTF-8 support
header('Content-Type: text/html; charset=utf-8');

// Load Composer autoload for FPDI/FPDF
require_once('vendor/autoload.php');

use setasign\Fpdi\Fpdi;

// Check for the leave application ID
if (!isset($_GET['id'])) {
    die('ไม่พบข้อมูลใบลา');
}
$applicationId = $_GET['id'];

// Database connection and query
include('connect.php');
mysqli_set_charset($conn, "utf8");

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

// Determine the correct template based on leave type
$leaveType = $leaveData['leave_type'];
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
    case 'ลาพักผ่อนไปต่างประเทศ':
        $templatePath = 'form/Form-ใบลาพักผ่อนไปต่างประเทศ.pdf';
        break;
    default:
        $templatePath = 'form/default_template.pdf';
        break;
}

if (!file_exists($templatePath)) {
    die("ไม่พบไฟล์ template: " . $templatePath);
}

// Create PDF using FPDI
$pdf = new Fpdi();
$pdf->AddPage();

// Load Thai font
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->SetFont('THSarabunNew', '', 16);

// Load PDF template
try {
    $pageCount = $pdf->setSourceFile($templatePath);
    $tplIdx = $pdf->importPage(1);
    $pdf->useTemplate($tplIdx);
} catch (Exception $e) {
    die("ไม่สามารถโหลดไฟล์ PDF: " . $e->getMessage());
}

$pdf->SetTextColor(0, 0, 0);

// Function to handle Thai encoding
function convertThai($text) {
    return iconv("UTF-8", "Windows-874//IGNORE", $text);
}

// Insert data based on the template
switch ($leaveType) {
    case 'ลาป่วย':
    case 'ลากิจส่วนตัว':
    case 'การลาคลอดบุตร':
        $pdf->SetXY(50, 56);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(50, 69);
        $pdf->Write(0, convertThai($leaveType)); // เช่น ลาป่วย, ลากิจส่วนตัว
        $pdf->SetXY(50, 83);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(120, 83);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        
        break;
    
    case 'ลาพักผ่อน':
        $pdf->SetXY(56, 56.5);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(47, 88);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(107, 88);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        break;
    
    case 'ขอยกเลิกวันลา':
        $pdf->SetXY(50, 50);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(50, 57);
        $pdf->Write(0, convertThai($leaveData['Position']));
        $pdf->SetXY(50, 71);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(120, 71);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        break;
    
    case 'ลาพักผ่อนไปต่างประเทศ':
        $pdf->SetXY(50, 40);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(50, 47);
        $pdf->Write(0, convertThai($leaveData['Position']));
        $pdf->SetXY(50, 61);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(120, 61);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        break;
}

// Common fields for all templates
$pdf->SetXY(50, 100);
$pdf->Write(0, convertThai('หมายเหตุ: ' . ($leaveData['Remarks'] ? $leaveData['Remarks'] : 'ไม่มี')));

// Output PDF to browser
$pdf->Output('I', 'ใบลา_' . $applicationId . '.pdf');
?>
