<?php
// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

include('connect.php');

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
$user_name = $_SESSION['Username'];
$stmt = $conn->prepare("SELECT firstname, lastname, role, profile_picture, CreatedAt, UserID FROM users WHERE username = ?");
$stmt->bind_param("s", $user_name);
$stmt->execute();
$result = $stmt->get_result();

$firstname = '';
$lastname = '';
$role = '';
$profile_picture = '';
$create_at = '';  // เก็บวันที่ CreateAt
$user_id = ''; // เก็บ UserID

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstname = htmlspecialchars($row['firstname']);
    $lastname = htmlspecialchars($row['lastname']);
    $role = htmlspecialchars($row['role']);
    $profile_picture = $row['profile_picture'];
    $create_at = $row['CreatedAt'];  // เก็บวันที่สร้างบัญชีผู้ใช้ (CreateAt)
    $user_id = $row['UserID'];  // เก็บ UserID
} else {
    echo "ไม่พบข้อมูลผู้ใช้";
    exit;
}

$stmt->close();

// คำนวณจำนวนวันตั้งแต่วันที่เข้าทำงาน (CreateAt)
$create_date = new DateTime($create_at);  // วันที่เริ่มงานจากฐานข้อมูล
$current_date = new DateTime();  // วันที่ปัจจุบัน
$days_since_join = $current_date->diff($create_date)->days;  // คำนวณจำนวนวัน

// ข้อมูลวันลาที่ใช้ไปแล้ว (สมมุติว่าดึงมาจากฐานข้อมูล)
$usedLeaveData = [];
$sql = "SELECT lt.LeaveName, lt.LeaveTypeID, SUM(DATEDIFF(la.EndDate, la.StartDate)) AS total_leave_days
        FROM leaveapplications la
        JOIN leavetypes lt ON la.LeaveTypeID = lt.LeaveTypeID
        WHERE la.UserID = ? AND la.ApprovalStatus = 'Approved'  -- กรองตาม UserID
        GROUP BY lt.LeaveTypeID";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);  // ใช้ UserID แทน
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $leaveTypeID = $row['LeaveTypeID'];
    $usedLeaveData[$leaveTypeID] = $row['total_leave_days'];
}

// ข้อมูลประเภทการลา (LeaveTypeID) และจำนวนวันลาทั้งหมด
$leaveTypesData = [];
$leaveTypesData[1] = ($days_since_join < 365) ? 30 : 120;  // ลาป่วย
$leaveTypesData[2] = ($days_since_join < 365) ? 15 : 45;   // ลากิจส่วนตัว
$leaveTypesData[3] = ($days_since_join < 183) ? 0 : 10;    // ลาพักผ่อน
$leaveTypesData[4] = 120;  // ลาบวช/ประกอบพิธีฮัจย์
$leaveTypesData[5] = 90;   // ลาไปถือศีลและปฏิบัติธรรม
$leaveTypesData[6] = 0;    // ไม่ระบุ
$leaveTypesData[7] = 0;    // ไม่ระบุ
$leaveTypesData[8] = 90;   // ลาคลอด
$leaveTypesData[9] = 150;  // การลากิจเพื่อเลี้ยงดูบุตร
$leaveTypesData[10] = 0;
$leaveTypesData[11] = 1460; // ลาติดตามคู่สมรส
$leaveTypesData[12] = 0;

// คำนวณวันลาคงเหลือโดยใช้เงื่อนไขที่ให้มา
$remainingLeaveData = [];
foreach ($leaveTypesData as $leaveTypeID => $totalLeaveDays) {
    $usedLeave = $usedLeaveData[$leaveTypeID] ?? 0;
    $remainingDays = $totalLeaveDays;

    switch ($leaveTypeID) {
        case 1: // ลาป่วย
            if ($days_since_join < 365) {
                $remainingDays = min(30, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 30 วันถ้าทำงานไม่ถึง 1 ปี
            } else {
                $remainingDays = min(120, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 120 วันถ้าทำงานเกิน 1 ปี
            }
            break;
        case 2: // ลากิจส่วนตัว
            if ($days_since_join < 365) {
                $remainingDays = min(15, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 15 วันถ้าทำงานไม่ถึง 1 ปี
            } else {
                $remainingDays = min(45, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 45 วันถ้าทำงานเกิน 1 ปี
            }
            break;
        case 3: // ลาพักผ่อน
            if ($days_since_join < 183) {
                $remainingDays = 0; // ลาไม่ได้ถ้าทำงานไม่ถึง 6 เดือน
            } else {
                if ($days_since_join >= 365) {
                    $remainingDays = min($totalLeaveDays + 10, 10) - $usedLeave; // ถ้าลาไม่ถึง 10 วันในปีแรก จะทบวัน
                } else {
                    $remainingDays = min(10, $totalLeaveDays) - $usedLeave;
                }
            }
            break;
        case 4: // ลาบวช/ประกอบพิธีฮัจย์
            $remainingDays = min(120, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 120 วัน
            break;
        case 5: // ลาไปถือศีลและปฏิบัติธรรม
            $remainingDays = min(90, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 90 วัน
            break;
        case 6:
            $remainingDays = min(0, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 90 วัน
            break;
        case 7:
            $remainingDays = min(0, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 90 วัน
            break;
        case 8: // ลาคลอดบุตร
            $remainingDays = min(90, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 90 วัน
            break;
        case 9: // การลากิจเพื่อเลี้ยงดูบุตร
            $remainingDays = min(150, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 150 วัน
            break;
        case 10:
            $remainingDays = min(0, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 90 วัน
            break;
        case 11: // ลาติดตามคู่สมรส
            $remainingDays = min(1460, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 1460 วัน
            break;
        case 12:
            $remainingDays = min(0, $totalLeaveDays) - $usedLeave; // ลาได้ไม่เกิน 90 วัน
            break;
        default:
            $remainingDays = $totalLeaveDays - $usedLeave;
            break;
    }

    $remainingLeaveData[$leaveTypeID] = $remainingDays;
}

// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลการลางาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-3">
        <h3 class="text-center">ข้อมูลวันลาของคุณ</h3>
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>ประเภทการลา</th>
                    <th>จำนวนวันที่ลาได้</th>
                    <th>วันที่ลาแล้ว</th>
                    <th>วันลาคงเหลือ</th>
                </tr>
            </thead>
            <tbody id="leaveTableBody">
                <?php foreach ($leaveTypesData as $leaveTypeID => $totalLeaveDays): ?>
                    <tr>
                        <td>
                            <?php
                            // สร้างชื่อประเภทการลาโดยใช้ LeaveTypeID
                            switch ($leaveTypeID) {
                                case 1:
                                    echo "ลาป่วย";
                                    break;
                                case 2:
                                    echo "ลากิจส่วนตัว";
                                    break;
                                case 3:
                                    echo "ลาพักผ่อน";
                                    break;
                                case 4:
                                    echo "ลาบวช/ประกอบพิธีฮัจย์";
                                    break;
                                case 5:
                                    echo "ลาไปถือศีลและปฏิบัติธรรม";
                                    break;
                                case 6:
                                    echo "ลาเข้ารับการตรวจเลือกหรือเข้ารับเตรียมพล";
                                    break;
                                case 7:
                                    echo "ลาดูแลบิดาหรือมารดา";
                                    break;
                                case 8:
                                    echo "ลาคลอดบุตร";
                                    break;
                                case 9:
                                    echo "การลากิจเพื่อเลี้ยงดูบุตร";
                                    break;
                                case 10:
                                    echo "ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร";
                                    break;
                                case 11:
                                    echo "ลาติดตามคู่สมรส";
                                    break;
                                case 12:
                                    echo "ลาพักผ่อนไปต่างประเทศ";
                                    break;
                                default:
                                    echo "ประเภทไม่ระบุ";
                                    break;
                            }
                            ?>
                        </td>
                        <td><?= $totalLeaveDays ?> วัน</td>
                        <td><?= $usedLeaveData[$leaveTypeID] ?? 0 ?> วัน</td>
                        <td><?= $remainingLeaveData[$leaveTypeID] ?> วัน</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>