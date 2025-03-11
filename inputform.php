<?php
// inputform.php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['UserID'])) {
    header("Location: login.php?error=กรุณาเข้าสู่ระบบก่อน");
    exit;
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 600px;
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
                <label class="form-label"><strong>ชื่อ:</strong>
                    <?= htmlspecialchars($firstName . ' ' . $lastName); ?></label>
            </div>

            <form action="submit_leave.php" method="POST" novalidate>
                <div class="mb-3">
                    <label for="leave_type" class="form-label">ประเภทการลา:</label>
                    <select id="leave_type" name="leave_type" class="form-select" required
                        onchange="fetchConditions(this.value)">
                        <option value="">-- เลือกประเภทการลา --</option>
                        <?php foreach ($leaveTypes as $type): ?>
                            <option value="<?= intval($type['LeaveTypeID']); ?>"
                                <?= $type['LeaveName'] == 'ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['LeaveName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="leave_conditions" class="mb-3">
                    <!-- แสดงเงื่อนไขการลา -->
                </div>

                <!-- ตัวเลือกสำหรับกรอกข้อมูล -->
                <div id="additional_options" class="mb-3" style="display: none;">
                    <label class="form-label">กรุณาเลือกตัวเลือก:</label><br>
                    <input type="radio" id="attachDocuments" name="document_option" value="attach"
                        onchange="toggleFileInput(true)"> แนบสำเนาสูติบัตรและทะเบียนสมรส
                    <input type="radio" id="sendDocuments" name="document_option" value="send"
                        onchange="toggleFileInput(false)"> ขอจัดส่งในวันแรกที่ข้าพกลับมา
                </div>

                <!-- ช่องสำหรับแนบไฟล์เมื่อเลือก "แนบเอกสาร" -->
                <div id="fileInputSection" class="mb-3" style="display: none;">
                    <label for="documents" class="form-label">แนบไฟล์:</label>
                    <input type="file" id="documents" name="documents" class="form-control">
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
                    <label for="remarks" class="form-label">เหตุผลการลา:</label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="4" placeholder="กรอกเหตุผลการลา"
                        maxlength="45" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="contact_info" class="form-label">ระหว่างลาติดต่อข้าพเจ้าได้ที่:</label>
                    <input type="text" id="contact_info" name="contact_info" class="form-control"
                        placeholder="กรอกข้อมูลการติดต่อ" maxlength="100">
                </div>
                <!-- ช่องกรอกข้อมูลสำหรับการมอบหมายงานระหว่างลา -->
                <div id="work_assignment_section" class="mb-3" style="display: none;">
                    <label for="assign_work" class="form-label">มอบหมายงานระหว่างลาให้:</label>
                    <input type="text" id="assign_work" name="assign_work" class="form-control"
                        placeholder="ขอมอบหมายให้" maxlength="100">
                </div>

                <!-- ช่องกรอกข้อมูลสำหรับผู้ปฏิบัติงานแทน -->
                <div id="work_replacement_section" class="mb-3" style="display: none;">
                    <label for="work_replacement" class="form-label">เป็นผู้ปฏิบัติงานแทน ดังนี้:</label>
                    <input type="text" id="work_replacement" name="work_replacement" class="form-control"
                        placeholder="งานที่มอบหมาย" maxlength="100">
                </div>

                <!-- ช่องกรอกข้อมูลสำหรับวันเกิด -->
                <div class="ordination_section mb-3" style="display: none;">
                    <label for="birth_date" class="form-label">วันเกิด:</label>
                    <input type="text" id="birth_date" name="birth_date" class="form-control"
                        placeholder="กรอกวันเกิด (เช่น 01 เมษายน 2565)" required>
                </div>


                <!-- ช่องกรอกข้อมูลสำหรับเคยอุปสมบทหรือไม่ -->
                <div class="ordination_section mb-3" style="display: none;">
                    <label for="ordination_status" class="form-label">เคยอุปสมบท:</label>
                    <select id="ordination_status" name="ordination_status" class="form-select">
                        <option value="เคยอุปสมบท">เคยอุปสมบท</option>
                        <option value="ยังไม่เคยอุปสมบท">ยังไม่เคยอุปสมบท</option>
                    </select>
                </div>

                <!-- ช่องกรอกข้อมูลสำหรับวัดที่อุปสมบท -->
                <div class="ordination_section mb-3" style="display: none;">
                    <label for="ordination_wat" class="form-label">วัดที่จะอุปสมบท:</label>
                    <input type="text" id="ordination_wat" name="ordination_wat" class="form-control"
                        placeholder="กรอกชื่อวัดที่อุปสมบท">
                </div>

                <!-- ช่องกรอกข้อมูลสำหรับที่อยู่วัด -->
                <div class="ordination_section mb-3" style="display: none;">
                    <label for="ordination_address" class="form-label">ที่อยู่วัด:</label>
                    <textarea id="ordination_address" name="ordination_address" class="form-control"
                        placeholder="กรอกที่อยู่วัด"></textarea>
                </div>

                <!-- ช่องกรอกข้อมูลสำหรับกำหนดวันที่จำพรรษา -->
                <div class="ordination_section mb-3" style="display: none;">
                    <label for="ordination_date" class="form-label">กำหนดวันที่จำพรรษา:</label>
                    <textarea id="ordination_date" name="ordination_date" class="form-control"
                        placeholder="กรอกวันที่ (เช่น 01 เมษายน 2565)"></textarea>
                </div>



                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <button type="submit" class="btn btn-primary w-100">บันทึก</button>

            </form>
            <a href="index.php" class="btn btn-danger mt-3 w-100">ย้อนกลับ</a>
        </div>
    </div>

    <!-- Bootstrap JS และ JavaScript สำหรับแสดงเงื่อนไขการลา -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันเพื่อแสดงหรือซ่อนตัวเลือกการแนบไฟล์ตามประเภทการลา
        function fetchConditions(leaveTypeID) {
            const additionalOptionsSection = document.getElementById('additional_options');
            const workAssignmentSection = document.getElementById('work_assignment_section'); // Section สำหรับมอบหมายงาน
            const workReplacementSection = document.getElementById('work_replacement_section');
            const ordinationSections = document.querySelectorAll('.ordination_section'); // เลือกทุกๆ ordination_section

            // ตรวจสอบว่าเลือกประเภทการลาเป็น "ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร"
            if (leaveTypeID == 10) { // LeaveTypeID สำหรับ "ลาเพื่อดูแลบุตรและภรรยาหลังคลอดบุตร"
                additionalOptionsSection.style.display = 'block'; // แสดงตัวเลือกเพิ่มเติม
            } else {
                additionalOptionsSection.style.display = 'none'; // ซ่อนตัวเลือก
            }

            // เพิ่มเงื่อนไขใหม่สำหรับ "ลาพักผ่อน"
            if (leaveTypeID == 3) { // สมมติว่า LeaveTypeID 3 คือ ลาพักผ่อน
                workAssignmentSection.style.display = 'block';  // แสดงฟอร์มมอบหมายงานระหว่างลา
                workReplacementSection.style.display = 'block';
            } else {
                workAssignmentSection.style.display = 'none';   // ซ่อนฟอร์มมอบหมายงาน
                workReplacementSection.style.display = 'none';
            }

            // เพิ่มเงื่อนไขสำหรับ "ลาบวช/ประกอบพิธีฮัจย์" และ "ลาไปถือศีลและปฏิบัติธรรม"
            if (leaveTypeID == 4 || leaveTypeID == 5) {  // สมมติว่า LeaveTypeID 4 คือ ลาบวช/ประกอบพิธีฮัจย์ และ LeaveTypeID 5 คือ ลาไปถือศีล
                ordinationSections.forEach(section => {
                    section.style.display = 'block';  // แสดงฟอร์มข้อมูลอุปสมบท
                });
            } else {
                ordinationSections.forEach(section => {
                    section.style.display = 'none';   // ซ่อนฟอร์มข้อมูลอุปสมบท
                });
            }

            // เรียก fetchConditions() เพื่อนำข้อมูลเงื่อนไขการลา
            if (leaveTypeID === "") {
                document.getElementById('leave_conditions').innerHTML = "";
                return;
            }
            fetch(`get_leave_conditions.php?leaveTypeID=${leaveTypeID}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('leave_conditions').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    if (data.length === 0) {
                        document.getElementById('leave_conditions').innerHTML = "<p>ไม่มีเงื่อนไขการลาในประเภทนี้</p>";
                        return;
                    }
                    let html = "<h5>เงื่อนไขการลา:</h5><ul>";
                    data.forEach(condition => {
                        html += `<li>${condition}</li>`;
                    });
                    html += "</ul>";
                    document.getElementById('leave_conditions').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('leave_conditions').innerHTML = `<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูลเงื่อนไขการลา</div>`;
                });
        }

        // ฟังก์ชันสำหรับการแสดง/ซ่อนช่องกรอกไฟล์
        function toggleFileInput(shouldShow) {
            const fileInputSection = document.getElementById('fileInputSection');
            if (shouldShow) {
                fileInputSection.style.display = 'block';  // แสดงช่องกรอกไฟล์
            } else {
                fileInputSection.style.display = 'none';   // ซ่อนช่องกรอกไฟล์
            }
        }
    </script>
</body>

</html>