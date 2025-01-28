<?php
// manage_users.php
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

// ดึงข้อมูลผู้ใช้งานทั้งหมด
$sql = "SELECT UserID, Username, FirstName, LastName, Email, Role, profile_picture FROM users";
$result = $conn->query($sql);
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน</title>
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
            max-width: 1000px;
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
        <h2 class="mb-4">จัดการผู้ใช้งาน</h2>
        <a href="create_user.php" class="btn btn-primary mb-3">สร้างบัญชีผู้ใช้งานใหม่</a>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>อีเมล์</th>
                        <th>บทบาท</th>
                        <th>โปรไฟล์</th>
                        <th>การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center">ไม่มีข้อมูลผู้ใช้งาน</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['Username']); ?></td>
                                <td><?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></td>
                                <td><?= htmlspecialchars($user['Email']); ?></td>
                                <td><?= htmlspecialchars($user['Role']); ?></td>
                                <td>
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <img src="<?= htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="img-thumbnail" width="100">
                                    <?php else: ?>
                                        ไม่ได้อัปโหลด
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_user.php?userid=<?= $user['UserID']; ?>" class="btn btn-sm btn-warning">แก้ไข</a>
                                    <a href="delete_user.php?userid=<?= $user['UserID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('คุณต้องการลบผู้ใช้งานนี้หรือไม่?');">ลบ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="index.php" class="btn btn-danger mt-3 w-20">ย้อนกลับ</a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
