<?php
// script_create_employees.php
include('connect.php');

// ดึง UserID ของผู้ใช้ที่มีบทบาท Employee แต่ไม่มีรายการในตาราง employees
$sql = "SELECT UserID, FirstName, LastName, Email, profile_picture 
        FROM users 
        WHERE Role = 'Employee' AND UserID NOT IN (SELECT EmployeeID FROM employees)";

$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows === 0) {
        echo "ไม่มีผู้ใช้ที่มีบทบาท Employee ที่ยังไม่มีในตาราง employees";
    } else {
        while ($row = $result->fetch_assoc()) {
            $userID = $row['UserID'];
            $firstName = $row['FirstName'];
            $lastName = $row['LastName'];
            $email = $row['Email'];
            $profile_picture = $row['profile_picture'];

            // กำหนดค่าเริ่มต้นสำหรับฟิลด์อื่นๆ
            $position = 'Unknown';
            $department = 'Unknown';
            $tel = 'Unknown';
            $full_name = $firstName . ' ' . $lastName;
            $role_employee = 'Employee';

            // แทรกข้อมูลในตาราง employees
            $stmt = $conn->prepare("INSERT INTO employees (EmployeeID, Position, Department, StartOfWork, Email, Tel, Name, profile_picture, role) 
                                    VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("issssss", $userID, $position, $department, $email, $tel, $full_name, $profile_picture, $role_employee);
                if ($stmt->execute()) {
                    echo "สร้าง Employee สำหรับ UserID: $userID สำเร็จ<br>";
                } else {
                    echo "ล้มเหลวในการสร้าง Employee สำหรับ UserID: $userID - " . $stmt->error . "<br>";
                }
                $stmt->close();
            } else {
                echo "เตรียมคำสั่ง SQL ล้มเหลวสำหรับ UserID: $userID - " . $conn->error . "<br>";
            }
        }
    }
} else {
    echo "ไม่พบผู้ใช้ที่มีบทบาท Employee ที่ไม่มีในตาราง employees";
}

$conn->close();
?>
