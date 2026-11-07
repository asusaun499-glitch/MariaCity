<?php
// Maria Town - Web Push Notification Server Endpoint & VAPID Handler
ini_set('display_errors', '0');
error_reporting(0);
ob_start();

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ==========================================
// VAPID Keys Configuration
// ==========================================
// ตัวอย่างคู่ VAPID Keys (NIST P-256 / prime256v1) สำหรับ Web Push บน HTTPS
// VAPID_PUBLIC_KEY: เผยแพร่ให้ Client ทำการ Subscribe
// VAPID_PRIVATE_KEY: เก็บรักษาเป็นความลับบน Server เพื่อเซ็น Authorization header
define('VAPID_PUBLIC_KEY', 'BKwK_hRz1hFhQ7-nQd9i3vQo0fP9n1H1L7v7fB7q_m8r8d8x7z7b6c5d4e3f2a1b9c8d7e6f5a4b3c2d1e0f');
define('VAPID_PRIVATE_KEY', 'e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2');
define('VAPID_SUBJECT', 'mailto:admin@mariacity.com');

$subscriptionsFile = DATA_DIR . '/push_subscriptions.json';
if (!file_exists(DATA_DIR)) {
    @mkdir(DATA_DIR, 0755, true);
}

function loadPushSubscriptions(): array {
    global $subscriptionsFile;
    if (!file_exists($subscriptionsFile)) return [];
    return json_decode(@file_get_contents($subscriptionsFile), true) ?: [];
}

function savePushSubscriptions(array $subs): bool {
    global $subscriptionsFile;
    return (bool)@file_put_contents($subscriptionsFile, json_encode($subs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? '';

// ==========================================
// 1. รับ VAPID Public Key ไปใช้ที่ Client
// ==========================================
if ($action === 'get_vapid_public_key') {
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'publicKey' => VAPID_PUBLIC_KEY
    ]);
    exit;
}

// ==========================================
// 2. บันทึก Subscription ของ Client (Subscribe)
// ==========================================
if ($action === 'subscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $endpoint = trim((string)($input['endpoint'] ?? ''));
    if (empty($endpoint) || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
        ob_end_clean();
        jsonResponse(['success' => false, 'message' => 'Invalid push endpoint'], 400);
    }

    $keys = $input['keys'] ?? [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $auth = trim((string)($keys['auth'] ?? ''));

    $user = currentUser();
    $uid = $user ? $user['uid'] : ('guest_' . substr(md5($_SERVER['REMOTE_ADDR'] ?? 'guest'), 0, 10));
    $role = $user ? ($user['role'] ?? 'user') : 'guest';

    $subs = loadPushSubscriptions();
    
    // อัปเดตหรือเพิ่ม subscription ตาม endpoint
    $found = false;
    foreach ($subs as &$sub) {
        if (($sub['endpoint'] ?? '') === $endpoint) {
            $sub['uid'] = $uid;
            $sub['role'] = $role;
            $sub['keys'] = ['p256dh' => $p256dh, 'auth' => $auth];
            $sub['updated_at'] = time();
            $found = true;
            break;
        }
    }
    unset($sub);

    if (!$found) {
        $subs[] = [
            'uid' => $uid,
            'role' => $role,
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => $p256dh, 'auth' => $auth],
            'created_at' => time(),
            'updated_at' => time()
        ];
    }

    savePushSubscriptions($subs);
    ob_end_clean();
    jsonResponse(['success' => true, 'message' => 'Push subscription registered successfully']);
}

// ==========================================
// 3. ส่ง Web Push Notification
// ==========================================
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // ต้องมีสิทธิ์ Admin หรือเป็น Internal Server Call
    $isInternal = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1');
    if (!isAdmin() && !$isInternal) {
        ob_end_clean();
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $targetUid = $input['target_uid'] ?? null;
    $title = trim((string)($input['title'] ?? 'Maria Town Roleplay'));
    $body = trim((string)($input['body'] ?? 'มีการแจ้งเตือนใหม่'));
    $url = trim((string)($input['url'] ?? '/'));
    $ticketId = $input['ticket_id'] ?? null;

    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => '/assets/logo-square.jpg',
        'badge' => '/assets/logo-square.jpg',
        'url' => $url,
        'ticket_id' => $ticketId,
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);

    $subs = loadPushSubscriptions();
    $sentCount = 0;
    $failedCount = 0;

    foreach ($subs as $sub) {
        // หากระบุ target_uid ให้ส่งเฉพาะผู้ใช้นั้น
        if ($targetUid && ($sub['uid'] ?? '') !== $targetUid) {
            continue;
        }

        $endpoint = $sub['endpoint'] ?? '';
        if (!$endpoint) continue;

        // ส่ง Push Notification ผ่าน HTTP Request ไปยัง Push Service Endpoint
        // (เช่น fcm.googleapis.com, push.services.mozilla.com, web.push.apple.com)
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'TTL: 86400',
            'Urgency: high'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $sentCount++;
        } else {
            $failedCount++;
        }
    }

    ob_end_clean();
    jsonResponse([
        'success' => true,
        'sent' => $sentCount,
        'failed' => $failedCount,
        'payload' => json_decode($payload, true)
    ]);
}

// Fallback response
ob_end_clean();
jsonResponse(['error' => 'Invalid action'], 400);

