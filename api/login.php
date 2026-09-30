<?php
// ปิด Error ทันทีที่ไฟล์เริ่มทำงาน
ini_set('display_errors', '0');
error_reporting(0);
ob_start(); // เปิด output buffer เพื่อดักจับ HTML warnings ที่อาจหลุดออกมา

require_once 'config.php';

// ต้องเป็น POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (empty($_POST['credential'])) {
    ob_end_clean();
    jsonResponse(['success' => false, 'message' => 'ไม่พบข้อมูล credential'], 400);
}

try {
    $jwt = $_POST['credential'];
    $parts = explode('.', $jwt);

    if (count($parts) !== 3) {
        throw new Exception('รูปแบบ credential ไม่ถูกต้อง');
    }

    // Base64URL decode payload ส่วน [1]
    $base64 = strtr($parts[1], '-_', '+/');
    $padded = str_pad($base64, (int)(ceil(strlen($base64) / 4) * 4), '=');
    $payload = json_decode(base64_decode($padded), true);

    if (!$payload || !isset($payload['email'])) {
        throw new Exception('ไม่สามารถอ่านข้อมูลจาก Google ได้');
    }

    // ตรวจสอบ Token หมดอายุ
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        throw new Exception('Token หมดอายุแล้ว กรุณาล็อกอินใหม่');
    }

    // สร้าง Session
    $_SESSION['user'] = [
        'uid'   => $payload['sub'] ?? uniqid('uid_'),
        'email' => $payload['email'],
        'name'  => $payload['name'] ?? 'ผู้เล่น Maria City',
        'photo' => $payload['picture'] ?? 'https://placehold.co/40x40/1a1a1a/ffffff?text=U',
        'role'  => ($payload['email'] === DEV_EMAIL) ? 'admin' : 'user',
    ];

    $redirectUrl = ($_SESSION['user']['role'] === 'admin') ? '/admin' : '/portal';

    ob_end_clean();
    jsonResponse([
        'success'      => true,
        'role'         => $_SESSION['user']['role'],
        'email'        => $_SESSION['user']['email'],
        'name'         => $_SESSION['user']['name'],
        'redirect'     => $redirectUrl,   // backward compat
        'redirect_url' => $redirectUrl,   // new field ที่ JS ใช้
    ]);

} catch (Throwable $e) {
    ob_end_clean();
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
