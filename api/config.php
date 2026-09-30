<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== CONFIGURATION ====================
define('DEV_EMAIL', 'asusaun499@gmail.com');
define('GOOGLE_CLIENT_ID', '575104946747-hu2nuk2sucjecirloj275lam2bt6u4m3.apps.googleusercontent.com');
define('DATA_DIR', __DIR__ . '/data');
define('UPLOADS_DIR', __DIR__ . '/data/uploads');

// สร้างโฟลเดอร์หากยังไม่มี
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0777, true);
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0777, true);

// ==================== HELPER FUNCTIONS ====================
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

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ==================== MOCK DATA (สำหรับทดสอบ) ====================
// Mock tickets สำหรับทดสอบระบบโดยไม่ต้องตั้งค่าเพิ่มเติม
function initMockData(): void {
    $ticketFile = DATA_DIR . '/tickets.json';
    if (!file_exists($ticketFile)) {
        $mockTickets = [
            [
                'id' => 'TKT-001',
                'type' => 'check_player',
                'status' => 'pending',
                'from_uid' => 'user_mock_001',
                'from_name' => 'TestPlayer01',
                'from_email' => 'testplayer01@gmail.com',
                'message' => 'ขอให้ตรวจสอบผู้เล่นชื่อ "SpeedHacker99" ว่าใช้โปรหรือเปล่า',
                'evidence' => [],
                'admin_reply' => null,
                'created_at' => time() - 3600,
                'updated_at' => time() - 3600,
            ],
            [
                'id' => 'TKT-002',
                'type' => 'report_bug',
                'status' => 'replied',
                'from_uid' => 'user_mock_002',
                'from_name' => 'CityRider22',
                'from_email' => 'cityrider22@gmail.com',
                'message' => 'พบบัคตก Map หลุดจากโลกเกมในโซน DownTown ทุกครั้งที่ขับรถสีแดง',
                'evidence' => [],
                'admin_reply' => 'รับทราบแล้วครับ ทีมกำลังตรวจสอบ จะแก้ไขใน Patch ถัดไปนะครับ',
                'created_at' => time() - 86400,
                'updated_at' => time() - 43200,
            ],
            [
                'id' => 'TKT-003',
                'type' => 'check_player',
                'status' => 'closed',
                'from_uid' => 'user_mock_003',
                'from_name' => 'MapWatcher',
                'from_email' => 'mapwatcher@gmail.com',
                'message' => 'ขอตรวจสอบผู้เล่น "NoClipMan" ที่เห็นวิ่งทะลุกำแพงได้',
                'evidence' => [],
                'admin_reply' => 'ตรวจสอบแล้วพบหลักฐานเพียงพอ ดำเนินการ Ban เรียบร้อยแล้วครับ',
                'created_at' => time() - 172800,
                'updated_at' => time() - 100000,
            ],
        ];
        file_put_contents($ticketFile, json_encode($mockTickets, JSON_UNESCAPED_UNICODE));
    }
}

initMockData();
