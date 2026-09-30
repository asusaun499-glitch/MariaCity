<?php
/**
 * config.php — Maria City Roleplay
 * กำหนดค่าส่วนกลาง, Helper Functions, และ Mock Data
 */

// ===== ปิด PHP Error Output ทั้งหมด (ป้องกัน HTML ปนกับ JSON) =====
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// ===== เริ่ม Session =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== CONFIGURATION =====
define('DEV_EMAIL',      'asusaun499@gmail.com');
define('GOOGLE_CLIENT_ID', '575104946747-hu2nuk2sucjecirloj275lam2bt6u4m3.apps.googleusercontent.com');
define('DATA_DIR',       __DIR__ . '/data');
define('UPLOADS_DIR',    __DIR__ . '/data/uploads');

// สร้างโฟลเดอร์อย่างปลอดภัย ไม่พ่น Warning
@mkdir(DATA_DIR,    0777, true);
@mkdir(UPLOADS_DIR, 0777, true);

// ===== HELPER FUNCTIONS =====
function isLoggedIn(): bool {
    return isset($_SESSION['user']);
}

function isAdmin(): bool {
    return isset($_SESSION['user']) && $_SESSION['user']['email'] === DEV_EMAIL;
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function redirectIfNotLoggedIn(): void {
    if (!isLoggedIn()) {
        header('Location: /');
        exit;
    }
}

function redirectIfNotAdmin(): void {
    redirectIfNotLoggedIn();
    if (!isAdmin()) {
        header('Location: /portal');
        exit;
    }
}

/**
 * ส่ง JSON Response และหยุดการทำงาน
 * ล้าง output buffer ก่อนเพื่อป้องกัน HTML ปนออกมา
 */
function jsonResponse(array $data, int $code = 200): void {
    // ล้างทุก output ที่อาจค้างอยู่ใน buffer
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
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
    $raw = @file_get_contents($file);
    return $raw ? (json_decode($raw, true) ?: []) : [];
}

function saveTickets(array $tickets): void {
    @file_put_contents(DATA_DIR . '/tickets.json', json_encode($tickets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ===== MOCK DATA (สำหรับทดสอบระบบทันที) =====
function initMockData(): void {
    $ticketFile = DATA_DIR . '/tickets.json';
    if (file_exists($ticketFile)) return;

    $mockTickets = [
        [
            'id'          => 'TKT-001',
            'type'        => 'check_player',
            'status'      => 'pending',
            'from_uid'    => 'user_mock_001',
            'from_name'   => 'TestPlayer01',
            'from_email'  => 'testplayer01@gmail.com',
            'message'     => 'ขอให้ตรวจสอบผู้เล่นชื่อ "SpeedHacker99" ว่าใช้โปรหรือเปล่า',
            'evidence'    => [],
            'admin_reply' => null,
            'created_at'  => time() - 3600,
            'updated_at'  => time() - 3600,
        ],
        [
            'id'          => 'TKT-002',
            'type'        => 'report_bug',
            'status'      => 'replied',
            'from_uid'    => 'user_mock_002',
            'from_name'   => 'CityRider22',
            'from_email'  => 'cityrider22@gmail.com',
            'message'     => 'พบบัคตก Map หลุดจากโลกเกมในโซน DownTown ทุกครั้งที่ขับรถสีแดง',
            'evidence'    => [],
            'admin_reply' => 'รับทราบแล้วครับ ทีมกำลังตรวจสอบ จะแก้ไขใน Patch ถัดไปนะครับ',
            'created_at'  => time() - 86400,
            'updated_at'  => time() - 43200,
        ],
        [
            'id'          => 'TKT-003',
            'type'        => 'check_player',
            'status'      => 'closed',
            'from_uid'    => 'user_mock_003',
            'from_name'   => 'MapWatcher',
            'from_email'  => 'mapwatcher@gmail.com',
            'message'     => 'ขอตรวจสอบผู้เล่น "NoClipMan" ที่วิ่งทะลุกำแพงได้',
            'evidence'    => [],
            'admin_reply' => 'ตรวจสอบแล้วพบหลักฐาน ดำเนินการ Ban เรียบร้อย',
            'created_at'  => time() - 172800,
            'updated_at'  => time() - 100000,
        ],
    ];

    @file_put_contents($ticketFile, json_encode($mockTickets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// เรียกใช้ Mock Data เฉพาะเมื่อโฟลเดอร์ data/ เขียนได้
if (is_writable(DATA_DIR)) {
    initMockData();
}
