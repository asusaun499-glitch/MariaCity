<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $jwt = $_POST['credential'];
    
    // ทำการ Decode JWT เพื่ออ่านข้อมูลโปรไฟล์จาก Google
    // หมายเหตุ: ในระบบ Production ควรใช้ไลบรารี firebase/php-jwt ตรวจสอบ Signature ด้วย
    $parts = explode('.', $jwt);
    if (count($parts) === 3) {
        $payload = json_decode(base64_decode($parts[1]), true);
        
        if ($payload && isset($payload['email'])) {
            // สร้าง Session ให้ผู้ใช้งาน
            $_SESSION['user'] = [
                'uid' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
                'photo' => $payload['picture']
            ];
            
            echo json_encode(['success' => true]);
            exit;
        }
    }
}
echo json_encode(['success' => false, 'message' => 'ข้อมูลประจำตัวไม่ถูกต้อง']);
