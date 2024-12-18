<?php
// inputform.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php?error=กรุณาเข้าสู่ระบบก่อน");
    exit();
}

// ตรวจสอบว่า FirstName และ LastName ถูกตั้งค่าไว้หรือไม่
$firstName = isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] : 'ไม่ทราบ';
$lastName = isset($_SESSION['LastName']) ? $_SESSION['LastName'] : 'ไม่ทราบ';

include('connect.php');

// ดึงข้อมูลประเภทการลา
$sql = "SELECT LeaveTypeID, LeaveName FROM leavetypes";
$result = $conn->query($sql);

$leaveTypes = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $leaveTypes[] = $row;
    }
}

$conn->close();

// สร้าง CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ตรวจสอบว่ามีข้อความแจ้งเตือนหรือไม่
$alertType = '';
$alertMessage = '';
if (isset($_GET['success'])) {
    $alertType = 'success';
    $alertMessage = htmlspecialchars($_GET['success']);
} elseif (isset($_GET['error'])) {
    $alertType = 'danger';
    $alertMessage = htmlspecialchars($_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ฟอร์มกรอกข้อมูลการลา</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f9;
            padding-top: 70px;
        }
        .form-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: auto;
        }
        .btn-back {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
            display: inline-block;
        }
        .btn-back:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="text-center mb-4">ฟอร์มกรอกข้อมูลการลา</h2>
            
            <!-- แสดงแจ้งเตือนถ้ามี -->
            <?php if ($alertType && $alertMessage): ?>
                <div class="alert alert-<?= $alertType; ?> alert-dismissible fade show" role="alert">
                    <?= $alertMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- แสดงข้อมูลผู้ใช้ปัจจุบัน -->
            <div class="mb-3">
                <label class="form-label"><strong>ชื่อ:</strong> <?= htmlspecialchars($firstName . ' ' . $lastName); ?></label>
            </div>

            <form action="submit_leave.php" method="POST" novalidate>
                <!-- ลบฟิลด์รหัสพนักงานออกไป -->

                <div class="mb-3">
                    <label for="leave_type" class="form-label">ประเภทการลา:</label>
                    <select id="leave_type" name="leave_type" class="form-select" required>
                        <option value="">-- เลือกประเภทการลา --</option>
                        <?php foreach ($leaveTypes as $type): ?>
                            <option value="<?= intval($type['LeaveTypeID']); ?>"><?= htmlspecialchars($type['LeaveName']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="start_date" class="form-label">วันที่เริ่มลา:</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="end_date" class="form-label">วันที่สิ้นสุดการลา:</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="remarks" class="form-label">หมายเหตุ:</label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="4" placeholder="กรอกเหตุผลการลา" required></textarea>
                </div>
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <button type="submit" class="btn btn-primary w-100">ส่งข้อมูล</button>
            </form>
            <a href="index.php" class="btn-back">ย้อนกลับ</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($alertType && $alertMessage): ?>
        <script>
            // หากต้องการแสดง JavaScript alert สามารถเพิ่มโค้ดนี้ได้
            // alert('<?= $alertMessage; ?>');
        </script>
    <?php endif; ?>
</body>
</html>
