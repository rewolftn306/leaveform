<?php
// ฟังก์ชันสำหรับส่งข้อความไปยัง Google Chat ผ่าน Webhook
function sendGoogleChatNotification($message, $role = null) {
    // กำหนด Webhook URL ตามบทบาท (Role)
    switch ($role) {
        case 'Leader':
            $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAAAxTmL5c8/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=dkahazacJYcS-ukaghDiMzMtr17DG6sI_K8ps5zOCxc'; // Webhook สำหรับ Leader
            break;
        case 'Leader2':
            $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAAAWBiaFU8/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=8dPUCn1xF7T3X3UJWrBqkLqZUT7g7xT6toJKatxQKi8'; // Webhook สำหรับ Leader2
            break;
        case 'Leader3':
            $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAAAfjhCb2U/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=RLbjlvkh4j97MyqDpXMNabJNiq_xCIMODCeUmuy8awQ'; // Webhook สำหรับ Leader3
            break;
        case 'Director':
            $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAAAe1UbyMk/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=YaHlRee5e6qxImMGZ6L6TVAPrTGKZiJNC5VHhnqJBsg'; // Webhook สำหรับ Director
            break;
        default:
            // Webhook ค่าเริ่มต้น
            $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAAAaBCuirU/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=B4GrHde2cf_gpcXXLc6mS6FmNuvvsxdTIJYXnj4btEY';
    }

    $data = [
        'text' => $message
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];

    // ส่งข้อมูลไปยัง Webhook
    $context = stream_context_create($options);
    $result = file_get_contents($webhookUrl, false, $context);

    if ($result === FALSE) {
        // Handle error
        error_log('Error sending Google Chat notification');
    }
}
?>
