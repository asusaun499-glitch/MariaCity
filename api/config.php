<?php
/**
 * config.php — Maria City Roleplay
 * ใช้ HMAC-Signed Cookie แทน PHP Session
 * เพื่อรองรับ Vercel Serverless (ไม่มี shared session storage)
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// ===== SECRET KEY (เปลี่ยนให้เป็นค่าสุ่มยาวๆ ของคุณเอง) =====
define('APP_SECRET',     'mc_r0lep14y_s3cr3t_k3y_2026_!@#$%^');
define('COOKIE_NAME',    'mc_auth');
define('COOKIE_TTL',     86400 * 7);   // 7 วัน
define('DEV_EMAIL',      'asusaun499@gmail.com');
define('GOOGLE_CLIENT_ID', '575104946747-hu2nuk2sucjecirloj275lam2bt6u4m3.apps.googleusercontent.com');
define('DATA_DIR',       __DIR__ . '/data');
define('UPLOADS_DIR',    __DIR__ . '/data/uploads');

@mkdir(DATA_DIR,    0777, true);
@mkdir(UPLOADS_DIR, 0777, true);

// ===== COOKIE AUTH FUNCTIONS =====

/** สร้าง Signed Cookie จาก user array */
function createAuthCookie(array $user): string {
    $payload   = base64_encode(json_encode($user, JSON_UNESCAPED_UNICODE));
    $signature = hash_hmac('sha256', $payload, APP_SECRET);
    return $payload . '.' . $signature;
}

/** ตรวจสอบ Cookie และคืนค่า user array หรือ null */
function verifyAuthCookie(): ?array {
    $cookie = $_COOKIE[COOKIE_NAME] ?? '';
    if (!$cookie) return null;

    $dotPos = strrpos($cookie, '.');
    if ($dotPos === false) return null;

    $payload   = substr($cookie, 0, $dotPos);
    $signature = substr($cookie, $dotPos + 1);

    // ตรวจสอบ HMAC signature
    $expected = hash_hmac('sha256', $payload, APP_SECRET);
    if (!hash_equals($expected, $signature)) return null;

    $user = json_decode(base64_decode($payload), true);
    if (!$user || !isset($user['email'])) return null;

    return $user;
}

/** Set Auth Cookie */
function setAuthCookie(array $user): void {
    $value = createAuthCookie($user);
    $isSecure = isset($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie(COOKIE_NAME, $value, [
        'expires'  => time() + COOKIE_TTL,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Clear Auth Cookie */
function clearAuthCookie(): void {
    setcookie(COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ===== SHORTCUT FUNCTIONS =====
function isLoggedIn(): bool {
    return verifyAuthCookie() !== null;
}

function isAdmin(): bool {
    $user = verifyAuthCookie();
    return $user && $user['email'] === DEV_EMAIL;
}

function currentUser(): ?array {
    return verifyAuthCookie();
}

function redirectIfNotLoggedIn(): void {
    // ถูกแทนที่ด้วย JS Auth Guard ในหน้า HTML เพื่อแก้ปัญหา Redirect Loop บน Vercel
}

function redirectIfNotAdmin(): void {
    // ถูกแทนที่ด้วย JS Auth Guard ในหน้า HTML
}

/** ส่ง JSON Response — ล้าง buffer ก่อนเสมอ */
function jsonResponse(array $data, int $code = 200): void {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== TICKET HELPERS =====
function loadTickets(): array {
    $file = DATA_DIR . '/tickets.json';
    if (!file_exists($file)) return [];
    return json_decode(@file_get_contents($file), true) ?: [];
}

function saveTickets(array $tickets): void {
    @file_put_contents(
        DATA_DIR . '/tickets.json',
        json_encode($tickets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

// ===== MOCK DATA =====
function initMockData(): void {
    $ticketFile = DATA_DIR . '/tickets.json';
    if (file_exists($ticketFile)) return;

    $mockTickets = [
        [
            'id' => 'TKT-001', 'type' => 'check_player', 'status' => 'pending',
            'from_uid' => 'user_mock_001', 'from_name' => 'TestPlayer01',
            'from_email' => 'testplayer01@gmail.com',
            'message' => 'ขอให้ตรวจสอบผู้เล่น "SpeedHacker99" ว่าใช้โปรหรือเปล่า',
            'evidence' => [], 'admin_reply' => null,
            'created_at' => time() - 3600, 'updated_at' => time() - 3600,
        ],
        [
            'id' => 'TKT-002', 'type' => 'report_bug', 'status' => 'replied',
            'from_uid' => 'user_mock_002', 'from_name' => 'CityRider22',
            'from_email' => 'cityrider22@gmail.com',
            'message' => 'พบบัคตก Map ในโซน DownTown ทุกครั้งที่ขับรถสีแดง',
            'evidence' => [],
            'admin_reply' => 'รับทราบแล้วครับ ทีมกำลังตรวจสอบ จะแก้ไขใน Patch ถัดไป',
            'created_at' => time() - 86400, 'updated_at' => time() - 43200,
        ],
        [
            'id' => 'TKT-003', 'type' => 'check_player', 'status' => 'closed',
            'from_uid' => 'user_mock_003', 'from_name' => 'MapWatcher',
            'from_email' => 'mapwatcher@gmail.com',
            'message' => 'ขอตรวจสอบผู้เล่น "NoClipMan" ที่วิ่งทะลุกำแพงได้',
            'evidence' => [],
            'admin_reply' => 'ตรวจสอบแล้วพบหลักฐาน ดำเนินการ Ban เรียบร้อย',
            'created_at' => time() - 172800, 'updated_at' => time() - 100000,
        ],
    ];

    @file_put_contents($ticketFile, json_encode($mockTickets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if (is_writable(DATA_DIR)) {
    initMockData();
}
