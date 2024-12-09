<?php
// การเชื่อมต่อฐานข้อมูล
include('connect.php');

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// ดึงข้อมูลประเภทการลา
$sql = "SELECT LeaveTypeID, LeaveName FROM leavetypes";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ฟอร์มกรอกข้อมูลการลา</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f4f4f9;
        }
        form {
            max-width: 400px;
            margin: auto;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        input, select, textarea {
            width: 95%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 16px;
            border-radius: 4px;
        }
        button:hover {
            background-color: #45a049;
        }
        .back-button {
            background-color: #f44336;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-bottom: 20px;
            display: inline-block;
        }
        .back-button:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>

<!-- ปุ่มย้อนกลับไปหน้า index.php -->
<a href="index.php" class="back-button">ย้อนกลับ</a>

<h2 style="text-align:center;">ฟอร์มกรอกข้อมูลการลา</h2>

<form action="submit_leave.php" method="post">

    <label for="empid">รหัสพนักงาน:</label>
    <input type="text" id="empid" name="empid" placeholder="กรอกรหัสพนักงาน" required>

    <label for="leave_type">ประเภทการลา:</label>
    <select id="leave_type" name="leave_type" required>
        <option value="">-- เลือกประเภทการลา --</option>
        <?php
        // แสดงตัวเลือกประเภทการลา
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<option value='" . $row['LeaveTypeID'] . "'>" . $row['LeaveName'] . "</option>";
            }
        } else {
            echo "<option value=''>ไม่มีข้อมูล</option>";
        }
        ?>
    </select>

    <label for="start_date">วันที่เริ่มลา:</label>
    <input type="date" id="start_date" name="start_date" required>

    <label for="end_date">วันที่สิ้นสุดการลา:</label>
    <input type="date" id="end_date" name="end_date" required>

    <label for="remarks">หมายเหตุ:</label>
    <textarea id="remarks" name="remarks" rows="4" placeholder="กรอกเหตุผลการลา" required></textarea>

    <button type="submit">ส่งข้อมูล</button>
</form>

<?php
// ปิดการเชื่อมต่อ
$conn->close();
?>

</body>
</html>
