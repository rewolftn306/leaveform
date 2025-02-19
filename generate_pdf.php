<?php
// เชื่อมต่อฐานข้อมูล
include('connect.php');
mysqli_set_charset($conn, "utf8");

// ตรวจสอบว่าได้รับ ApplicationID หรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ไม่พบ ApplicationID');
}

$applicationId = $_GET['id'];

// ตรวจสอบค่า ApplicationID
echo "ApplicationID: " . htmlspecialchars($applicationId);  // แสดง ApplicationID ที่ได้รับจาก URL

// ดึงข้อมูลจากฐานข้อมูล
$stmt = $conn->prepare("SELECT u.UserID, u.FirstName, u.LastName, e.Position AS EmployeePosition, e.Department AS EmployeeDepartment, e.Email AS EmployeeEmail, e.Tel AS EmployeeTel, e.Name AS EmployeeName, la.StartDate, la.EndDate, la.Remarks, lt.LeaveName AS LeaveType 
                        FROM leaveapplications la 
                        JOIN users u ON la.EmployeeID = u.UserID
                        JOIN employees e ON e.UserID = u.UserID
                        LEFT JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
                        WHERE la.ApplicationID = ?");
$stmt->bind_param("i", $applicationId);  // ใช้ "i" สำหรับ integer ที่เป็น ApplicationID
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('ไม่พบข้อมูลใบลา');
}

$leaveData = $result->fetch_assoc();
$stmt->close();
$conn->close();

// ใช้ FPDI สำหรับการสร้าง PDF
require_once('vendor/autoload.php');
use setasign\Fpdi\Fpdi;

$pdf = new Fpdi();
$pdf->AddPage();

// กำหนดฟอนต์ไทย
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php');
$pdf->SetFont('THSarabunNew', '', 14);

// เลือกฟอร์มที่เหมาะสม
$leaveType = $leaveData['LeaveType'];
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

// โหลดเทมเพลต PDF
$pdf->setSourceFile($templatePath);
$tplIdx = $pdf->importPage(1);
$pdf->useTemplate($tplIdx);

// ฟังก์ชันแปลงวันที่เป็นไทย
function thaiDate($date) {
    $thaiMonth = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    $d = date_create($date);
    return date_format($d, 'j') . ' ' . $thaiMonth[date_format($d, 'n')-1] . ' ' . (date_format($d, 'Y') + 543);
}

// กำหนดตำแหน่งกรอกข้อมูล
$fields = [
    'employee_name' => [56, 56.5],
    'employee_position' => [78, 64],
    'employee_department' => [82, 71],
    'start_date' => [47, 88],
    'end_date' => [107, 88],
    'leave_type' => [52, 96],
    'employee_tel' => [52, 102]
];

// กรอกข้อมูลลงในฟอร์ม
$pdf->SetTextColor(0, 0, 0); // สีข้อความ
foreach ($fields as $field => $coordinates) {
    $pdf->SetXY($coordinates[0], $coordinates[1]);
    switch ($field) {
        case 'employee_name':
            $pdf->Write(0, convertThai($leaveData['EmployeeName']));
            break;
        case 'employee_position':
            $pdf->Write(0, convertThai($leaveData['EmployeePosition']));
            break;
        case 'employee_department':
            $pdf->Write(0, convertThai($leaveData['EmployeeDepartment']));
            break;
        case 'start_date':
            $pdf->Write(0, convertThai(thaiDate($leaveData['StartDate'])));
            break;
        case 'end_date':
            $pdf->Write(0, convertThai(thaiDate($leaveData['EndDate'])));
            break;
        case 'leave_type':
            $pdf->Write(0, convertThai($leaveData['LeaveType']));
            break;
        case 'employee_tel':
            $pdf->Write(0, convertThai($leaveData['EmployeeTel']));
            break;
    }
}

// ฟังก์ชันแปลงข้อความเป็นภาษาไทย
function convertThai($text) {
    return iconv("UTF-8", "Windows-874//IGNORE", $text);
}

// ส่งออก PDF
$pdf->Output('I', 'ใบลา_' . $applicationId . '.pdf');
?>
