<?php
// manage_approvers.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['Username'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบบทบาทของผู้ใช้
if ($_SESSION['Role'] !== 'Admin') {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

include('connect.php');

// ดึงข้อมูลผู้อนุมัติทั้งหมด
$sql = "SELECT u.UserID, u.Username, u.FirstName, u.LastName, u.Role, a.ApproverID
        FROM users u
        JOIN approvers a ON u.UserID = a.UserID
        WHERE u.Role IN ('Director', 'Admin')";

$result = $conn->query($sql);
$approvers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $approvers[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้อนุมัติ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f4f9;
            padding-top: 70px;
        }
        .manage-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: auto;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <span class="navbar-brand">ระบบการลาของบุคลากร มหาวิทยาลัยมหาสารคาม</span>
            <div class="d-flex">
                <span class="navbar-text me-3">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['Username']); ?></span>
                <form method="POST" action="logout.php" class="d-inline">
                    <button type="submit" name="logout" class="btn btn-outline-light">ออกจากระบบ</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container manage-container">
        <h2 class="mb-4">จัดการผู้อนุมัติ</h2>
        <a href="create_user.php" class="btn btn-primary mb-3">สร้างบัญชีผู้อนุมัติใหม่</a>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>บทบาท</th>
                        <th>ประเภทผู้อนุมัติ</th>
                        <th>การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($approvers)): ?>
                        <tr>
                            <td colspan="5" class="text-center">ไม่มีข้อมูลผู้อนุมัติ</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($approvers as $approver): ?>
                            <tr>
                                <td><?= htmlspecialchars($approver['Username']); ?></td>
                                <td><?= htmlspecialchars($approver['FirstName'] . ' ' . $approver['LastName']); ?></td>
                                <td><?= htmlspecialchars($approver['Role']); ?></td>
                                <td>
                                    <?php
                                        switch ($approver['ApproverID']) {
                                            case 1:
                                                echo 'อธิบดี';
                                                break;
                                            case 2:
                                                echo 'รองอธิการดี, คณบดี, ผู้อำนวยการสถาบัน, ผู้อำนวยการสำนัก, หัวหน้าหน่วยงานเทียบเท่าคณะ';
                                                break;
                                            case 3:
                                                echo 'ผู้ที่มีอำนวยการกองหรือหัวหน้าหน่วยเทียบเท่ากอง';
                                                break;
                                            case 4:
                                                echo 'หัวหน้าภาควิชาหรือหัวหน้าหน่วยงานเทียบเท่าภาควิชา';
                                                break;
                                            default:
                                                echo 'ไม่ระบุ';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <a href="edit_user.php?userid=<?= $approver['UserID']; ?>" class="btn btn-sm btn-warning">แก้ไข</a>
                                    <a href="delete_user.php?userid=<?= $approver['UserID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณต้องการลบผู้ใช้งานนี้หรือไม่?');">ลบ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
