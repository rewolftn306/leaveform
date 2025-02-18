<?php
// Set UTF-8 encoding for the page
header('Content-Type: text/html; charset=utf-8');

// Load Composer autoload for FPDI/FPDF
require_once('vendor/autoload.php');

use setasign\Fpdi\Fpdi;

// Check for the required parameter
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

// Determine the leave type and corresponding template
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
$pdf->SetFont('THSarabunNew', '', 14);

// Load PDF template
try {
    $pageCount = $pdf->setSourceFile($templatePath);
    $tplIdx = $pdf->importPage(1);
    $pdf->useTemplate($tplIdx);
} catch (Exception $e) {
    die("ไม่สามารถโหลดไฟล์ PDF: " . $e->getMessage());
}

$pdf->SetTextColor(0, 0, 0);

// Function to convert Thai text
function convertThai($text) {
    return iconv("UTF-8", "Windows-874//IGNORE", $text);
}

// Function for Thai date formatting
function thaiDate($date) {
    $thaiMonth = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    $d = date_create($date);
    return date_format($d, 'j') . ' ' . 
           $thaiMonth[date_format($d, 'n')-1] . ' ' . 
           (date_format($d, 'Y') + 543);
}

// Set coordinates based on leave type
switch ($leaveType) {
    case 'ลาป่วย':
    case 'ลากิจส่วนตัว':
    case 'การลาคลอดบุตร':
        $fields = [
            'name' => [56, 56.5],
            'position' => [78, 64],
            'department' => [82, 71],
            'start_date' => [47, 88],
            'end_date' => [107, 88],
            'days' => [150, 88],
            'reason' => [52, 96]
        ];
        break;
    case 'ลาพักผ่อน':
        $fields = [
            'name' => [56, 56.5],
            'position' => [78, 64],
            'department' => [82, 71],
            'start_date' => [47, 88],
            'end_date' => [107, 88],
            'days' => [150, 88],
            'contact' => [52, 96]
        ];
        break;
    case 'ลาพักผ่อนไปต่างประเทศ':
        $fields = [
            'name' => [45, 82],
            'position' => [45, 90],
            'start_date' => [45, 105],
            'end_date' => [100, 105],
            'days' => [150, 105],
            'contact' => [45, 115],
            'delegation' => [45, 145]
        ];
        break;
    // Add other leave types as needed
}

// Fill in the form fields
foreach ($fields as $field => $coordinates) {
    $pdf->SetXY($coordinates[0], $coordinates[1]);
    switch ($field) {
        case 'name':
            $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
            break;
        case 'start_date':
            $pdf->Write(0, convertThai(thaiDate($leaveData['StartDate'])));
            break;
        case 'end_date':
            $pdf->Write(0, convertThai(thaiDate($leaveData['EndDate'])));
            break;
        case 'reason':
        case 'contact':
            $pdf->Write(0, convertThai($leaveData['Remarks']));
            break;
        // Add more fields as needed
    }
}

// Handle checkboxes for specific leave types
if ($leaveType === 'ลาป่วย' || $leaveType === 'ลากิจส่วนตัว' || $leaveType === 'การลาคลอดบุตร') {
    $pdf->SetFont('ZapfDingbats', '', 14);
    $checkboxX = 40;
    $checkboxY = $leaveType === 'ลาป่วย' ? 50 : ($leaveType === 'ลากิจส่วนตัว' ? 55 : 60);
    $pdf->SetXY($checkboxX, $checkboxY);
    $pdf->Write(0, '4'); // Checkmark character
}

// Output the PDF
$pdf->Output('I', 'ใบลา_' . $applicationId . '.pdf');
?>
