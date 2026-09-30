<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Add or update FAQ entries here. The AI matches user messages against keywords.
$information = [
    [
        'question' => 'สมัครเข้าเล่นเซิร์ฟเวอร์อย่างไร',
        'keywords' => ['สมัคร', 'เข้าเล่น', 'เข้าร่วม', 'whitelist', 'ไวท์ลิสต์'],
        'answer' => 'สมัคร Whitelist ได้ที่ Discord: https://discord.gg/8rh4b4eu79 แล้วทำตามขั้นตอนในห้องสมัคร Whitelist ครับ',
    ],
    [
        'question' => 'Discord ของ Maria City อยู่ที่ไหน',
        'keywords' => ['discord', 'ดิสคอร์ด', 'ลิงก์'],
        'answer' => 'เข้าร่วม Discord ของ Maria City ได้ที่ https://discord.gg/8rh4b4eu79 ครับ',
    ],
    [
        'question' => 'แจ้งบัคหรือปัญหาอย่างไร',
        'keywords' => ['แจ้งบัค', 'บัค', 'bug', 'ปัญหา', 'error', 'ข้อผิดพลาด'],
        'answer' => 'เลือกเมนู “แชทกับทีมงาน” แล้วส่งรายละเอียดปัญหาได้เลยครับ แนบภาพหลักฐานประกอบได้ ทีมงานจะตอบกลับใน ticket นี้',
    ],
    [
        'question' => 'แจ้งตรวจสอบผู้เล่นอย่างไร',
        'keywords' => ['ตรวจสอบผู้เล่น', 'ตรวจสอบคน', 'ใช้โปร', 'โกง', 'cheat', 'hack', 'report'],
        'answer' => 'ส่งชื่อผู้เล่น เวลาและสถานที่ที่พบเหตุการณ์ พร้อมรายละเอียดหรือภาพหลักฐานใน “แชทกับทีมงาน” ได้เลยครับ',
    ],
    [
        'question' => 'ประวัติ ticket อยู่ที่ไหน',
        'keywords' => ['ประวัติ', 'ticket', 'ทิคเก็ต', 'แชทเก่า'],
        'answer' => 'เปิดเมนู “ประวัติแชท” เพื่อดู ticket เก่าและเปิดบทสนทนาต่อได้ครับ',
    ],
];

$approvedKnowledge = json_decode(@file_get_contents(DATA_DIR . '/ai_knowledge.json'), true) ?: [];
foreach ($approvedKnowledge as $entry) {
    if (!empty($entry['question']) && !empty($entry['answer']) && is_array($entry['keywords'] ?? null)) {
        $information[] = $entry;
    }
}

$questions = json_decode(@file_get_contents(DATA_DIR . '/ai_questions.json'), true) ?: [];
$cutoff = time() - 14 * 86400;
$frequentQuestions = array_values(array_map(
    fn($item) => ['question' => (string)($item['question'] ?? ''), 'count' => (int)($item['count'] ?? 0)],
    array_filter($questions, fn($item) => ($item['status'] ?? 'pending') === 'pending' && (int)($item['last_seen'] ?? 0) >= $cutoff && (int)($item['count'] ?? 0) >= 3)
));

echo json_encode(['information' => $information, 'frequent_questions' => $frequentQuestions], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
