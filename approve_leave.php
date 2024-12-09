<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือยัง
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

// เชื่อมต่อฐานข้อมูล
include('connect.php');

if ($conn->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// ฟังก์ชันค้นหาข้อมูลการลา
$search_query = '';
if (isset($_POST['search_term'])) {
    $search_term = $conn->real_escape_string($_POST['search_term']);
    $search_query = "WHERE ENAME LIKE '%$search_term%'"; // ตัวอย่างการค้นหาจากชื่อ
}

// ดึงข้อมูลการขอลาจากฐานข้อมูล
$sql = "SELECT * FROM leaveapplications $search_query";
$result = $conn->query($sql);

// ตรวจสอบว่ามีข้อมูลหรือไม่
$leaves = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $leaves[] = $row;
    }
}

// ตรวจสอบการอนุมัติคำขอ
if (isset($_POST['approve_leave'])) {
    $leave_id = $_POST['leave_id'];
    $status = 'Approved'; // หรือสามารถตั้งค่าสถานะที่ต้องการ

    // อัปเดตสถานะการลาในฐานข้อมูล
    $update_sql = "UPDATE leaveapplications SET status = ? WHERE leave_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param('si', $status, $leave_id);

    if ($stmt->execute()) {
        header("Location: approve_leave.php?success=1"); // รีเฟรชหน้าหลังการอนุมัติ
        exit;
    } else {
        echo "การอนุมัติลาล้มเหลว: " . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การค้นหาข้อมูล</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            padding: 20px;
            margin: 0;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .logo {
            font-size: 20px;
            font-weight: bold;
        }
        .header .user-info {
            display: flex;
            align-items: center;
        }
        .header .user-info span {
            margin-right: 10px;
        }
        .header .user-info button {
            background-color: #4CAF50;
            color: white;
            padding: 6px 12px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        .header .user-info button:hover {
            opacity: 0.8;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            margin-top: 20px;
        }
        .search-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .search-bar input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            width: 80%;
        }
        .search-bar button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        .search-bar button:hover {
            opacity: 0.8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table-footer {
            display: flex;
            justify-content: space-between;
            padding: 10px;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">ระบบการลาของบุคลากร มหาวิทยาลัยมหาสารคาม</div>
    <div class="user-info">
        <span>ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['Username']); ?></span>
        <form method="POST" action="logout.php">
            <button type="submit" name="logout">ออกจากระบบ</button>
        </form>
    </div>
</div>

<div class="container">

    <!-- ค้นหาข้อมูล -->
    <form method="POST">
        <div class="search-bar">
            <input type="text" name="search_term" placeholder="ค้นหาข้อมูล..." value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>">
            <button type="submit">ค้นหา</button>
        </div>
    </form>

    <!-- ตารางแสดงผล -->
    <table>
        <thead>
            <tr>
                <th>ลำดับ</th>
                <th>ชื่อ</th>
                <th>ประเภท</th>
                <th>เวลา</th>
                <th>สถานะ</th>
                <th>การดำเนินการ</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (empty($leaves)) {
                echo "<tr><td colspan='6'>ไม่พบข้อมูล</td></tr>";
            } else {
                foreach ($leaves as $index => $leave) {
                    $name = htmlspecialchars($leave['ENAME'] ?? 'ไม่พบชื่อ');
                    $type = htmlspecialchars($leave['leave_type'] ?? 'ไม่พบประเภท');
                    $time = htmlspecialchars($leave['leave_time'] ?? 'ไม่พบเวลา');
                    $status = htmlspecialchars($leave['status'] ?? 'ไม่พบสถานะ');
                
                    echo "<tr>
                            <td>" . ($index + 1) . "</td>
                            <td>" . $name . "</td>
                            <td>" . $type . "</td>
                            <td>" . $time . "</td>
                            <td>" . $status . "</td>
                            <td>
                                <form method='POST'>
                                    <input type='hidden' name='leave_id' value='" . htmlspecialchars($leave['leave_id'] ?? '') . "'>
                                    <button type='submit' name='approve_leave'>อนุมัติ</button>
                                </form>
                            </td>
                        </tr>";
                }
            }
            ?>
        </tbody>
    </table>

    <div class="table-footer">
        <div>แสดงผลจากข้อมูล 10 รายการจากทั้งหมด <?php echo count($leaves); ?> รายการ</div>
        <button>โหลดเพิ่มเติม</button>
    </div>

</div>

</body>
</html>
