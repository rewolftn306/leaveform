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

$stmt = $conn->prepare("SELECT la.*, u.FirstName, u.LastName, IFNULL(lt.LeaveName, 'ไม่ระบุ') as leave_type, 
                               e.Position, e.Department, e.Tel, la.CreateDate
                        FROM leaveapplications la 
                        JOIN users u ON la.EmployeeID = u.UserID 
                        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID 
                        LEFT JOIN employees e ON la.EmployeeID = e.EmployeeID
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
    case 'ลาเข้ารับการตรวจเลือกหรือเข้ารับเตรียมพล':
    case 'ลาดูแลบิดาหรือมารดา':
    case 'การลากิจเพื่อเลี้ยงดูบุตรต่อเนื่องจากการคลอดบุตร':
        $templatePath = 'form/Form-ใบลาป่วย-ลากิจส่วนตัว-ลาคลอดบุตร_2568-2.pdf';
        break;
    case 'ลาบวช/ประกอบพิธีฮัจย์':
    case 'ลาไปถือศีลและปฏิบัติธรรม':
        $templatePath = 'form/ใบลาอุปสมบท.pdf';
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
    case 'ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร':
        $templatePath = 'form/ใบลาดูแลบุตรและภรรยาหลังคลอด.pdf';
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

function convertToThaiDate($date) {
    // อาเรย์ของชื่อเดือนในภาษาไทย
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];

    // แปลงวันที่ให้เป็น Timestamp (Unix Timestamp)
    $timestamp = strtotime($date);
    
    // ดึงวัน, เดือน, ปี
    $day = date('d', $timestamp);
    $month = date('n', $timestamp); // ใช้ 'n' เพื่อดึงเดือนแบบตัวเลข (1-12)
    $year = date('Y', $timestamp) + 543; // เพิ่ม 543 ปีให้เป็นปีพุทธศักราช

    // สร้างวันที่ในรูปแบบภาษาไทย โดยเพิ่มช่องว่างระหว่างวัน, เดือน, และปี
    return $day . '             ' . $thaiMonths[$month] . '           ' . $year;
}

function drawTick($pdf, $x, $y, $isChecked) {
    if ($isChecked) {
        // วาดเครื่องหมายติ๊กถูก
        $pdf->SetLineWidth(0.4); // กำหนดความหนาของเส้น
        $pdf->Line($x, $y + 1, $x + 2, $y + 3); // เส้นแรก
        $pdf->Line($x + 2, $y + 3, $x + 6, $y); // เส้นที่สอง
    }
}

// Insert data based on the template
switch ($leaveType) {
    case 'ลาป่วย':
    case 'ลากิจส่วนตัว':
    case 'การลาคลอดบุตร':
    case 'ลาเข้ารับการตรวจเลือกหรือเข้ารับเตรียมพล':
    case 'ลาดูแลบิดาหรือมารดา':
    case 'การลากิจเพื่อเลี้ยงดูบุตรต่อเนื่องจากการคลอดบุตร':
        // แปลงวันที่เป็นภาษาไทย
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $formattedDate = convertToThaiDate($leaveData['CreateDate']);
        $pdf->SetXY(130, 33.5);
        $pdf->Write(0, convertThai('' . $formattedDate));
        $pdf->SetXY(130, 56.75);
        $pdf->Write(0, convertThai('' . $leaveData['Position']));
        $pdf->SetXY(70, 63);
        $pdf->Write(0, convertThai('' . $leaveData['Department']));
        $pdf->SetXY(148, 107.75);
        $pdf->Write(0, convertThai('' . $leaveData['Tel']));
        $pdf->SetXY(56, 56.75);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . '  ' . $leaveData['LastName']));
        switch ($leaveType) {
            case 'ลาป่วย':
                // ติ๊กที่ช่อง "ลาป่วย"
                drawTick($pdf, 56, 68, true); // ตำแหน่งของ "ลาป่วย" checkbox
                drawTick($pdf, 52.25, 93.5, true); // ตำแหน่งของ "ลาป่วย" checkbox
                $remarksText = $leaveData['Remarks'] ? $leaveData['Remarks'] : 'ไม่มีหมายเหตุ';
                $pdf->SetXY(88, 69.5);  // ปรับตำแหน่งของหมายเหตุในช่อง "ลาป่วย"
                $pdf->Write(0, convertThai('' . $remarksText));
                break;
            
            case 'ลากิจส่วนตัว':
                // ติ๊กที่ช่อง "ลากิจส่วนตัว"
                drawTick($pdf, 56, 74.5, true); // ตำแหน่งของ "ลากิจส่วนตัว" checkbox
                $remarksText = $leaveData['Remarks'] ? $leaveData['Remarks'] : 'ไม่มีหมายเหตุ';
                $pdf->SetXY(97,76);  // ปรับตำแหน่งของหมายเหตุในช่อง "ลากิจส่วนตัว"
                $pdf->Write(0, convertThai('' . $remarksText));
                break;
        
            case 'การลาคลอดบุตร':
                // ติ๊กที่ช่อง "การลาคลอดบุตร" (ถ้ามี)
                drawTick($pdf, 56, 81, true); // ตำแหน่งของ "การลาคลอดบุตร" checkbox
                break;
        
        }
        $pdf->SetXY(61, 39.75);
        $pdf->Write(0, convertThai($leaveType)); // เช่น ลาป่วย, ลากิจส่วนตัว
        $pdf->SetXY(48, 88.5);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(108, 88.5);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        break;

    case 'ลาบวช/ประกอบพิธีฮัจย์':
    case 'ลาไปถือศีลและปฏิบัติธรรม':
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $formattedDate = convertToThaiDate($leaveData['CreateDate']);
        $pdf->SetXY(124, 35.25);
        $pdf->Write(0, convertThai('' . $formattedDate));
        $pdf->SetXY(130, 71);
        $pdf->Write(0, convertThai('' . $leaveData['Position']));
        $pdf->SetXY(40, 79.5);
        $pdf->Write(0, convertThai('' . $leaveData['Department']));
        $pdf->SetXY(58, 73);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(110, 120);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(38, 128);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        break;

    case 'ลาพักผ่อน':
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $formattedDate = convertToThaiDate($leaveData['CreateDate']);
        $pdf->SetXY(129, 35);
        $pdf->Write(0, convertThai('' . $formattedDate));
        $pdf->SetXY(130, 62.5);
        $pdf->Write(0, convertThai('' . $leaveData['Position']));
        $pdf->SetXY(40, 69);
        $pdf->Write(0, convertThai('' . $leaveData['Department']));
        $pdf->SetXY(56, 62.5);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(66, 82);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(118, 82);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        $pdf->SetXY(148, 94);
        $pdf->Write(0, convertThai('' . $leaveData['Tel']));
        break;
    
    case 'ขอยกเลิกวันลา':
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
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
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(50, 40);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(50, 47);
        $pdf->Write(0, convertThai($leaveData['Position']));
        $pdf->SetXY(50, 61);
        $pdf->Write(0, convertThai($leaveData['StartDate']));
        $pdf->SetXY(120, 61);
        $pdf->Write(0, convertThai($leaveData['EndDate']));
        break;

    case 'ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร':
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
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


// Output PDF to browser
$pdf->Output('I', 'ใบลา_' . $applicationId . '.pdf');
?>

