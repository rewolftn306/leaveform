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

// ดึงข้อมูลชื่อและบทบาทจากตาราง users
$stmt = $conn->prepare("SELECT CONCAT(u.FirstName, ' ', u.LastName) AS FullName, u.Role
                        FROM users u 
                        WHERE u.UserID = ?");
$stmt->bind_param("i", $_SESSION['UserID']); // ใช้ UserID จาก session
$stmt->execute();
$stmt->bind_result($fullName, $role);
$stmt->fetch();
$stmt->close();

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

function convertThai($text)
{
    return iconv("UTF-8", "Windows-874//IGNORE", $text);
}

// ฟังก์ชันแปลงเดือนเป็นภาษาไทย
function convertMonthToThai($month)
{
    $months = array(
        1 => "มกราคม",
        2 => "กุมภาพันธ์",
        3 => "มีนาคม",
        4 => "เมษายน",
        5 => "พฤษภาคม",
        6 => "มิถุนายน",
        7 => "กรกฎาคม",
        8 => "สิงหาคม",
        9 => "กันยายน",
        10 => "ตุลาคม",
        11 => "พฤศจิกายน",
        12 => "ธันวาคม"
    );
    return $months[$month];
}

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function convertDateToThai($date)
{
    list($day, $month, $year) = explode('/', $date);  // แยกวันที่จากรูปแบบ (วัน/เดือน/ปี)
    $thaiMonth = convertMonthToThai(intval($month));
    $thaiYear = $year + 543;  // เพิ่ม 543 ปี เพื่อให้เป็นปีไทย
    return $day . " " . $thaiMonth . " " . $thaiYear;
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
// กำหนดวันที่ปัจจุบัน
$currentDate = date('d/m/Y');  // รูปแบบวันที่ (วัน/เดือน/ปี)
$thaiDate = convertDateToThai($currentDate);  // แปลงวันที่เป็นภาษาไทย

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

// กำหนดขนาดของฟอนต์และตำแหน่งการแสดงผล
$pdf->SetFont('THSarabunNew', '', 16);

// กำหนดขนาดของลายเซ็น
$signatureWidth = 30;  // ความกว้าง
$signatureHeight = 10; // ความสูง

// กำหนดตำแหน่งเริ่มต้นของลายเซ็น
$signatureX = 100;  // ตำแหน่งเริ่มต้นของลายเซ็น
$signatureY = 123;  // ตำแหน่งเริ่มต้นของลายเซ็น

// ตรวจสอบ LeaveTypeID และปรับตำแหน่งลายเซ็น
if (in_array($leaveTypeID, [1, 2, 6, 7, 8, 9, 11])) {
    if ($current_role == 'Leader') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 178);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(140, 184.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(140, 191);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 165; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader2') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(45, 197);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(48, 203.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 209.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 185; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader3') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(50, 231.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(58, 238);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 244.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 216; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Director') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 235.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(142, 242);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(137, 248.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 225; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
        drawTick($pdf, 133, 211, true);
    }
}

if (in_array($leaveTypeID, [4, 5])) {
    if ($current_role == 'Leader') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 178);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(140, 184.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(140, 191);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 165; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader2') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(45, 197);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(48, 203.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 209.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 185; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader3') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(50, 231.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(58, 238);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 244.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 216; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Director') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 235.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(142, 242);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(137, 248.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 225; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
        drawTick($pdf, 133, 211, true);
    }
}

if ($leaveTypeID == 3) {
    if ($current_role == 'Leader') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(138, 161);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(138, 167.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 148; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader2') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(45, 173.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(48, 180);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 50;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 160; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader3') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(50, 201.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(58, 208);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 214.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 186; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Director') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 208.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(142, 215);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(137, 221.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 198; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
        drawTick($pdf, 134, 181, true);
    }
}

if ($leaveTypeID == 12){
    if ($current_role == 'Leader') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 178);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(140, 184.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(140, 191);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 165; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader2') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(45, 197);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(48, 203.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 209.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 185; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader3') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(50, 231.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(58, 238);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 244.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 216; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Director') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 235.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(142, 242);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(137, 248.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 225; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
        drawTick($pdf, 133, 211, true);
    }
}

if ($leaveTypeID == 10){
    if ($current_role == 'Leader') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 178);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(140, 184.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(140, 191);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 165; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader2') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(45, 197);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(48, 203.5);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 209.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 185; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Leader3') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(50, 231.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(58, 238);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(44, 244.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 52;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 216; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
    } elseif ($current_role == 'Director') {
        $pdf->SetTextColor(0, 0, 255);  // เปลี่ยนเป็นสีหมึกน้ำเงิน
        $pdf->SetXY(136, 235.5);
        $pdf->Write(0, convertThai('' . $fullName));
        $pdf->SetXY(142, 242);
        $pdf->Write(0, convertThai('' . $current_role));
        $pdf->SetXY(137, 248.5);
        $pdf->Write(0, convertThai($thaiDate));
        $signatureX = 145;  // ตัวอย่างการปรับตำแหน่ง X สำหรับ Leader
        $signatureY = 225; // ตัวอย่างการปรับตำแหน่ง Y สำหรับ Leader
        drawTick($pdf, 133, 211, true);
    }
}

// แทรกลายเซ็นในไฟล์ PDF
$pdf->Image($tempImageFile, $signatureX, $signatureY, $signatureWidth, $signatureHeight); // แทรกลายเซ็นที่ตำแหน่งที่กำหนด

// ลบไฟล์ชั่วคราวหลังจากใช้งานเสร็จ
unlink($tempPdfFile);
unlink($tempImageFile);

$pdfData = $pdf->Output('I');
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

?>