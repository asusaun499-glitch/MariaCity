<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['credential'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

$jwt = $_POST['credential'];
$parts = explode('.', $jwt);

if (count($parts) !== 3) {
    jsonResponse(['success' => false, 'message' => 'Invalid credential format'], 400);
}

// Base64 URL Decode payload
$payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);

if (!$payload || !isset($payload['email'])) {
    jsonResponse(['success' => false, 'message' => 'ไม่สามารถอ่านข้อมูลจาก Google ได้'], 400);
}

// ตรวจสอบว่า Token ยังไม่หมดอายุ
if (isset($payload['exp']) && $payload['exp'] < time()) {
    jsonResponse(['success' => false, 'message' => 'Token หมดอายุแล้ว กรุณาล็อกอินใหม่'], 401);
}

// สร้าง Session ผู้ใช้งาน
$_SESSION['user'] = [
    'uid'   => $payload['sub'],
    'email' => $payload['email'],
    'name'  => $payload['name'] ?? 'ผู้เล่น Maria City',
    'photo' => $payload['picture'] ?? 'https://placehold.co/40x40/1a1a1a/ffffff?text=U',
    'role'  => ($payload['email'] === DEV_EMAIL) ? 'admin' : 'user',
];

// ส่ง Redirect URL ตาม Role กลับไป
$redirect = ($_SESSION['user']['role'] === 'admin') ? '/admin' : '/portal';
jsonResponse(['success' => true, 'redirect' => $redirect, 'role' => $_SESSION['user']['role']]);
