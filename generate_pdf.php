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
                               u.Position, u.Department, u.Tel, u.CreatedAt, la.CreateDate, u.Signature
                        FROM leaveapplications la 
                        JOIN users u ON la.UserID = u.UserID
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
function convertThai($text)
{
    return iconv("UTF-8", "Windows-874//IGNORE", $text);
}

function convertToThaiDate($date, $isCreateDate = false, $isBirthDate = false)
{
    // อาเรย์ของชื่อเดือนในภาษาไทย
    $thaiMonths = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];

    // แปลงวันที่ให้เป็น Timestamp (Unix Timestamp)
    $timestamp = strtotime($date);

    // ดึงวัน, เดือน, ปี
    $day = date('d', $timestamp);
    $month = date('n', $timestamp); // ใช้ 'n' เพื่อดึงเดือนแบบตัวเลข (1-12)
    $year = date('Y', $timestamp) + 543; // เพิ่ม 543 ปีให้เป็นปีพุทธศักราช

    // หากเป็น CreateDate ให้เว้นวรรคเพียง 1 ช่อง
    if ($isCreateDate) {
        return $day . '             ' . $thaiMonths[$month] . '               ' . $year;
    } elseif ($isBirthDate) {
        return $day . '             ' . $thaiMonths[$month] . '                   ' . $year; // วันเกิดให้เว้นช่องระหว่างวัน เดือน ปี 1 ช่อง
    } else {
        // สำหรับ StartDate และ EndDate ให้เว้นวรรค 2 ช่อง
        return $day . '  ' . $thaiMonths[$month] . '  ' . $year;
    }
}

function drawTick($pdf, $x, $y, $isChecked)
{
    if ($isChecked) {
        // วาดเครื่องหมายติ๊กถูก
        $pdf->SetLineWidth(0.4); // กำหนดความหนาของเส้น
        $pdf->Line($x, $y + 1, $x + 2, $y + 3); // เส้นแรก
        $pdf->Line($x + 2, $y + 3, $x + 6, $y); // เส้นที่สอง
    }
}

function calculateLeaveDays($startDate, $endDate)
{
    // แปลงวันที่ให้เป็น Timestamp (Unix Timestamp)
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);

    // คำนวณจำนวนวัน
    $diffInDays = ($endTimestamp - $startTimestamp) / (60 * 60 * 24) + 0;

    return $diffInDays;
}

// ฟังก์ชันเพื่อดึงข้อมูลการลาครั้งล่าสุดสำหรับประเภทที่กำหนด
function getLastLeaveByType($userId, $leaveTypeId, $conn)
{
    // ดึงข้อมูลการลาครั้งล่าสุดที่มีสถานะเป็น "Approved"
    $sql = "SELECT * FROM leaveapplications 
            WHERE UserID = ? AND LeaveTypeID = ? AND ApprovalStatus = 'Approved'
            ORDER BY CreateDate DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $leaveTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();  // ส่งกลับข้อมูลการลาครั้งล่าสุด
    }
    return null;  // หากไม่มีการลาครั้งล่าสุดที่สถานะ Approved
}


// ดึงข้อมูลการลาครั้งล่าสุดตามประเภทการลา
$lastLeaveData = null;
if ($leaveType == 'ลาป่วย') {
    $lastLeaveData = getLastLeaveByType($leaveData['UserID'], 1, $conn);  // 1 = ลาป่วย
} elseif ($leaveType == 'ลากิจส่วนตัว') {
    $lastLeaveData = getLastLeaveByType($leaveData['UserID'], 2, $conn);  // 2 = ลากิจส่วนตัว
} elseif ($leaveType == 'การลาคลอดบุตร') {
    $lastLeaveData = getLastLeaveByType($leaveData['UserID'], 3, $conn);  // 3 = การลาคลอดบุตร
}



// ถ้ามีข้อมูลการลาครั้งล่าสุด
if ($lastLeaveData) {
    $lastLeaveStartDate = convertToThaiDate($lastLeaveData['StartDate']);
    $lastLeaveEndDate = convertToThaiDate($lastLeaveData['EndDate']);
    $lastLeaveRemarks = htmlspecialchars($lastLeaveData['Remarks']);

    // คำนวณจำนวนวันลา
    $lastleaveDays = calculateLeaveDays($lastLeaveData['StartDate'], $lastLeaveData['EndDate']);
}

// ดึงข้อมูลลายเซ็นจากฐานข้อมูล
$stmt = $conn->prepare("SELECT Signature FROM users WHERE UserID = ?");
$stmt->bind_param("i", $leaveData['UserID']);
$stmt->execute();
$stmt->bind_result($signatureBlob);
$stmt->fetch();
$stmt->close();

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

// กำหนดขนาดของลายเซ็น
$signatureWidth = 30;  // ความกว้าง
$signatureHeight = 10; // ความสูง

// รับตัวแปรจาก URL
$contactInfo = isset($_GET['contact_info']) ? $_GET['contact_info'] : '';
$assignWork = isset($_GET['assign_work']) ? $_GET['assign_work'] : '';
$workReplacement = isset($_GET['work_replacement']) ? $_GET['work_replacement'] : '';
$birthDate = isset($_GET['birth_date']) ? $_GET['birth_date'] : '';
$ordinationStatus = isset($_GET['ordination_status']) ? $_GET['ordination_status'] : '';
$ordinationWat = isset($_GET['ordination_wat']) ? $_GET['ordination_wat'] : '';
$ordinationDate = isset($_GET['ordination_date']) ? $_GET['ordination_date'] : '';
$ordinationAddress = isset($_GET['ordination_address']) ? $_GET['ordination_address'] : '';

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
        $formattedDate = convertToThaiDate($leaveData['CreateDate'], true);
        $formattedStartDate = convertToThaiDate($leaveData['StartDate']);
        $formattedEndDate = convertToThaiDate($leaveData['EndDate']);
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
        $pdf->SetXY(128, 137.5);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . '  ' . $leaveData['LastName']));
        $leaveDays = calculateLeaveDays($leaveData['StartDate'], $leaveData['EndDate']);
        $pdf->SetXY(175, 88.5);  // ปรับตำแหน่งของจำนวนวันที่ต้องการแสดง
        $pdf->Write(0, convertThai($leaveDays . ' '));
        $pdf->Image($tempImageFile, 135, 123, $signatureWidth, $signatureHeight);
        $pdf->SetXY(28, 108);
        $pdf->Write(0, convertThai(' ' . $contactInfo));
        $pdf->SetXY(155, 95);
        $pdf->Write(0, '' . convertThai($lastLeaveStartDate));
        $pdf->SetXY(43, 101.25);
        $pdf->Write(0, '' . convertThai($lastLeaveEndDate));
        $pdf->SetXY(99, 101.25);  // ปรับตำแหน่งที่ต้องการ
        $pdf->Write(0, '' . $lastleaveDays . '');


        switch ($leaveType) {
            case 'ลาป่วย':
                // ติ๊กที่ช่อง "ลาป่วย"
                drawTick($pdf, 56, 68, true); // ตำแหน่งของ "ลาป่วย" checkbox
                drawTick($pdf, 52.25, 93.5, true); // ตำแหน่งของ "ลาป่วย" checkbox
                $remarksText = $leaveData['Remarks'] ? $leaveData['Remarks'] : '';
                $pdf->SetXY(88, 69.5);  // ปรับตำแหน่งของหมายเหตุในช่อง "ลาป่วย"
                $pdf->Write(0, convertThai('' . $remarksText));
                break;

            case 'ลาเข้ารับการตรวจเลือกหรือเข้ารับเตรียมพล':
            case 'ลากิจส่วนตัว':
            case 'ลาดูแลบิดาหรือมารดา':
            case 'การลากิจเพื่อเลี้ยงดูบุตรต่อเนื่องจากการคลอดบุตร':
                // ติ๊กที่ช่อง "ลากิจส่วนตัว"
                drawTick($pdf, 56, 74.5, true); // ตำแหน่งของ "ลากิจส่วนตัว" checkbox
                drawTick($pdf, 69.5, 93.5, true);
                $remarksText = $leaveData['Remarks'] ? $leaveData['Remarks'] : '';
                $pdf->SetXY(97, 76);  // ปรับตำแหน่งของหมายเหตุในช่อง "ลากิจส่วนตัว"
                $pdf->Write(0, convertThai('' . $remarksText));
                break;

            case 'การลาคลอดบุตร':
                // ติ๊กที่ช่อง "การลาคลอดบุตร" (ถ้ามี)
                drawTick($pdf, 56, 81, true); // ตำแหน่งของ "การลาคลอดบุตร" checkbox
                drawTick($pdf, 95.5, 93.5, true);
                break;

        }
        $pdf->SetXY(61, 39.75);
        $pdf->Write(0, convertThai($leaveType)); // เช่น ลาป่วย, ลากิจส่วนตัว
        $pdf->SetXY(48, 88.5);
        $pdf->Write(0, convertThai($formattedStartDate));
        $pdf->SetXY(108, 88.5);
        $pdf->Write(0, convertThai($formattedEndDate));
        break;

    case 'ลาบวช/ประกอบพิธีฮัจย์':
    case 'ลาไปถือศีลและปฏิบัติธรรม':
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $formattedDate = convertToThaiDate($leaveData['CreateDate'], true);
        $formattedStartDate = convertToThaiDate($leaveData['StartDate']);
        $formattedEndDate = convertToThaiDate($leaveData['EndDate']);
        $formattedBirthDate = convertToThaiDate($birthDate, false, true);  // ใช้ $isBirthDate = true
        $formattedCreatedAtDate = convertToThaiDate($leaveData['CreatedAt']);
        $pdf->SetXY(124, 35.25);
        $pdf->Write(0, convertThai('' . $formattedDate));
        $pdf->SetXY(143, 29);
        $pdf->Write(0, convertThai('คณะวิทยาการสารสนเทศ'));
        $pdf->SetXY(130, 71);
        $pdf->Write(0, convertThai('' . $leaveData['Position']));
        $pdf->SetXY(40, 79.5);
        $pdf->Write(0, convertThai('' . $leaveData['Department']));
        $pdf->SetXY(146, 83.25);
        $pdf->Write(0, convertThai('' . $formattedCreatedAtDate));
        $pdf->SetXY(58, 73);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(110, 120);
        $pdf->Write(0, convertThai('' . $formattedStartDate));
        $pdf->SetXY(38, 128);
        $pdf->Write(0, convertThai('' . $formattedEndDate));
        $pdf->SetXY(117, 156.5);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->Image($tempImageFile, 119, 142, $signatureWidth, $signatureHeight);
        $pdf->SetXY(41, 85);
        $pdf->Write(0, convertThai('' . $formattedBirthDate));
        if ($ordinationStatus == "เคยอุปสมบท") {
            drawTick($pdf, 92, 90, true);  // วาดเครื่องหมายติ๊กที่ตำแหน่ง (56, 81)
        }if ($ordinationStatus == "ยังไม่เคยอุปสมบท") {
            drawTick($pdf, 49, 91, true);  // วาดเครื่องหมายติ๊กที่ตำแหน่ง (56, 81)
        }
        $pdf->SetXY(37, 97.5);
        $pdf->Write(0, convertThai('' . $ordinationWat));
        $pdf->SetXY(45, 110);
        $pdf->Write(0, convertThai('' . $ordinationDate));
        $pdf->SetXY(28, 103);
        $pdf->Write(0, convertThai('' . $ordinationAddress));
        $pdf->SetXY(127, 107.5);
        $pdf->Write(0, convertThai('' . $ordinationWat));
        $pdf->SetXY(45, 115.5);
        $pdf->Write(0, convertThai('' . $ordinationAddress));
        break;

    case 'ลาพักผ่อน':
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $formattedDate = convertToThaiDate($leaveData['CreateDate'], true);
        $formattedStartDate = convertToThaiDate($leaveData['StartDate']);
        $formattedEndDate = convertToThaiDate($leaveData['EndDate']);
        $pdf->SetXY(129, 35);
        $pdf->Write(0, convertThai('' . $formattedDate));
        $pdf->SetXY(130, 62.5);
        $pdf->Write(0, convertThai('' . $leaveData['Position']));
        $pdf->SetXY(40, 69);
        $pdf->Write(0, convertThai('' . $leaveData['Department']));
        $pdf->SetXY(56, 62.5);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(66, 82);
        $pdf->Write(0, convertThai('' . $formattedStartDate));
        $pdf->SetXY(118, 82);
        $pdf->Write(0, convertThai('' . $formattedEndDate));
        $pdf->SetXY(148, 94);
        $pdf->Write(0, convertThai('' . $leaveData['Tel']));
        $pdf->SetXY(101, 123.5);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->Image($tempImageFile, 110, 109, $signatureWidth, $signatureHeight);
        $pdf->SetXY(80, 88);
        $pdf->Write(0, convertThai(' ' . $contactInfo));
        $pdf->SetXY(146, 234);
        $pdf->Write(0, convertThai('' . $assignWork));
        $pdf->SetXY(71, 240.5);
        $pdf->Write(0, convertThai('' . $workReplacement));
        $pdf->SetXY(137, 272.25);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->Image($tempImageFile, 140, 258, $signatureWidth, $signatureHeight);
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
        $formattedDate = convertToThaiDate($leaveData['CreateDate'], true);
        $formattedStartDate = convertToThaiDate($leaveData['StartDate']);
        $formattedEndDate = convertToThaiDate($leaveData['EndDate']);
        $formattedCreatedAtDate = convertToThaiDate($leaveData['CreatedAt']);
        $pdf->SetXY(129, 39.5);
        $pdf->Write(0, convertThai('' . $formattedDate));
        $pdf->SetXY(135, 67);
        $pdf->Write(0, convertThai('' . $leaveData['Position']));
        $pdf->SetXY(40, 73.5);
        $pdf->Write(0, convertThai('' . $leaveData['Department']));
        $pdf->SetXY(56, 67);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(148, 98.5);
        $pdf->Write(0, convertThai('' . $leaveData['Tel']));
        $pdf->SetXY(66, 86);
        $pdf->Write(0, convertThai('' . $formattedStartDate));
        $pdf->SetXY(118, 86);
        $pdf->Write(0, convertThai('' . $formattedEndDate));
        $leaveDays = calculateLeaveDays($leaveData['StartDate'], $leaveData['EndDate']);
        $pdf->SetXY(175, 86);  // ปรับตำแหน่งของจำนวนวันที่ต้องการแสดง
        $pdf->Write(0, convertThai($leaveDays . ' '));
        $pdf->SetXY(140, 134);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->Image($tempImageFile, 145, 119.5, $signatureWidth, $signatureHeight);
        break;

    case 'ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร':
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $formattedDate = convertToThaiDate($leaveData['CreateDate'], true);
        $formattedStartDate = convertToThaiDate($leaveData['StartDate']);
        $formattedEndDate = convertToThaiDate($leaveData['EndDate']);
        $formattedCreatedAtDate = convertToThaiDate($leaveData['CreatedAt']);
        $pdf->SetXY(121, 34.5);
        $pdf->Write(0, convertThai('' . $formattedDate));
        $pdf->SetXY(137, 28.5);
        $pdf->Write(0, convertThai('คณะวิทยาการสารสนเทศ'));
        $pdf->SetXY(160, 70);
        $pdf->Write(0, convertThai('' . $leaveData['Position']));
        $pdf->SetXY(40, 79);
        $pdf->Write(0, convertThai('' . $leaveData['Department']));
        $pdf->SetXY(55, 72);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->SetXY(122, 96.5);
        $pdf->Write(0, convertThai('' . $leaveData['Tel']));
        $pdf->SetXY(94, 84);
        $pdf->Write(0, convertThai('' . $formattedStartDate));
        $pdf->SetXY(140, 83);
        $pdf->Write(0, convertThai('' . $formattedEndDate));
        $leaveDays = calculateLeaveDays($leaveData['StartDate'], $leaveData['EndDate']);
        $pdf->SetXY(40, 92);  // ปรับตำแหน่งของจำนวนวันที่ต้องการแสดง
        $pdf->Write(0, convertThai($leaveDays . ' '));
        $pdf->SetXY(114, 154.5);
        $pdf->Write(0, convertThai($leaveData['FirstName'] . ' ' . $leaveData['LastName']));
        $pdf->Image($tempImageFile, 119, 139.5, $signatureWidth, $signatureHeight);
        break;
}

// ลบไฟล์ชั่วคราวหลังจากใช้งานเสร็จ
unlink($tempImageFile);
//Get the content of PDF in memory as binary data
$pdfData = $pdf->Output('S');

// Update the database with the PDF file data
include('connect.php');
$stmt = $conn->prepare("UPDATE leaveapplications SET PDFFile = ? WHERE ApplicationID = ?");
$stmt->bind_param("bi", $null, $applicationId);

// For BLOB data, we use null as placeholder and then send the binary content
$stmt->send_long_data(0, $pdfData); // Send the binary PDF data
$stmt->execute();
$stmt->close();
$conn->close();

// Display success message and redirect to index.php
//echo "<script>alert('ส่งแบบฟอร์มสำเร็จแล้ว'); window.location.href = 'index.php';</script>";

//for debug
$pdf->Output('I', 'ใบลา_' . $applicationId . '.pdf');
?>