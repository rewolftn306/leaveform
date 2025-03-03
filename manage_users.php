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
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            padding-top: 70px;
        }

        .manage-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            margin: auto;
        }

        .table-container {
            margin-top: 20px;
        }

        .btn-primary,
        .btn-danger,
        .btn-warning {
            font-size: 14px;
            padding: 8px 16px;
        }

        .img-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
        }

        .table-dark th {
            background-color: #343a40;
            color: #fff;
        }

        .table th,
        .table td {
            vertical-align: middle;
        }

        .no-data-message {
            text-align: center;
            font-size: 18px;
            color: #888;
        }

        .modal-body img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
        }

        .modal-body .details {
            display: flex;
            flex-direction: column;
            margin-left: 20px;
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
        <a href="user_information.php" class="btn btn-secondary mb-3">สรุปการลา</a> <!-- New button here -->
        <div class="table-responsive table-container">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>อีเมล์</th>
                        <th>บทบาท</th>
                        <th>การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="no-data-message">ไม่มีข้อมูลผู้ใช้งาน</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['Username']); ?></td>
                                <td><?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></td>
                                <td><?= htmlspecialchars($user['Email']); ?></td>
                                <td><?= htmlspecialchars($user['Role']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#userDetailModal"
                                        data-userid="<?= $user['UserID']; ?>"
                                        data-username="<?= htmlspecialchars($user['Username']); ?>"
                                        data-firstname="<?= htmlspecialchars($user['FirstName']); ?>"
                                        data-lastname="<?= htmlspecialchars($user['LastName']); ?>"
                                        data-email="<?= htmlspecialchars($user['Email']); ?>"
                                        data-role="<?= htmlspecialchars($user['Role']); ?>"
                                        data-profile_picture="<?= htmlspecialchars($user['profile_picture']); ?>">
                                        ดูรายละเอียด
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <a href="index.php" class="btn btn-danger mt-3 w-20">ย้อนกลับ</a>
    </div>

    <!-- Modal for user details -->
    <div class="modal fade" id="userDetailModal" tabindex="-1" aria-labelledby="userDetailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userDetailModalLabel">รายละเอียดผู้ใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div style="display: flex;">
                        <img id="userProfilePicture" src="" alt="Profile Picture">
                        <div class="details">
                            <p><strong>ชื่อผู้ใช้:</strong> <span id="username"></span></p>
                            <p><strong>ชื่อ-นามสกุล:</strong> <span id="fullname"></span></p>
                            <p><strong>อีเมล์:</strong> <span id="email"></span></p>
                            <p><strong>บทบาท:</strong> <span id="role"></span></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="edit_user.php?userid=" id="editUserBtn" class="btn btn-warning">แก้ไข</a>
                        <a href="delete_user.php?userid=<?= $user['UserID']; ?>" class="btn btn-danger"
                            onclick="return confirm('คุณต้องการลบผู้ใช้งานนี้หรือไม่?');">ลบ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // แสดงข้อมูลผู้ใช้งานใน modal เมื่อคลิกปุ่ม "ดูรายละเอียด"
        const userDetailModal = document.getElementById('userDetailModal');
        userDetailModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // ปุ่มที่คลิก
            const userId = button.getAttribute('data-userid');
            const username = button.getAttribute('data-username');
            const firstName = button.getAttribute('data-firstname');
            const lastName = button.getAttribute('data-lastname');
            const email = button.getAttribute('data-email');
            const role = button.getAttribute('data-role');
            const profilePicture = button.getAttribute('data-profile_picture');

            // ตั้งค่าข้อมูลใน modal
            document.getElementById('userProfilePicture').src = profilePicture ? profilePicture : 'default-profile.jpg';
            document.getElementById('username').textContent = username;
            document.getElementById('fullname').textContent = `${firstName} ${lastName}`;
            document.getElementById('email').textContent = email;
            document.getElementById('role').textContent = role;

            // ตั้งค่าปุ่มแก้ไขและลบ
            document.getElementById('editUserBtn').setAttribute('href', `edit_user.php?userid=${userId}`);
            document.getElementById('deleteUserBtn').setAttribute('href', `delete_user.php?userid=${userId}`);
        });
    </script>
</body>

</html>
