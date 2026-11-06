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
$mapFile = DATA_DIR . '/map.json';
$parentMapFile = dirname(__DIR__) . '/data/map.json';
$mapData = file_exists($mapFile) ? (json_decode(@file_get_contents($mapFile), true) ?: []) : [];
$mapProgress = (int)($mapData['progress'] ?? 0);
if ($mapProgress <= 0 && file_exists($parentMapFile)) {
    $parentData = json_decode(@file_get_contents($parentMapFile), true) ?: [];
    $mapProgress = (int)($parentData['progress'] ?? 0);
}
if ($mapProgress <= 0) {
    $mapProgress = 70;
}

// Add or update FAQ entries here. The AI matches user messages against keywords.
$information = [
    [
        'question' => 'Maria Town / Maria City Roleplay คืออะไร',
        'keywords' => ['mariatown', 'maria town', 'ทาวน์', 'เมือง', 'เซิร์ฟ', 'คืออะไร', 'เซิฟเวอร์', 'ประวัติ', 'เกี่ยวกับ'],
        'answer' => "👑 **Maria Town (Maria City Roleplay)** คือเซิร์ฟเวอร์ Roleplay คุณภาพสูง ที่เน้นความสนุก ความสมจริงของสตอรี่ และสังคมที่เป็นมิตร มีระบบอาชีพที่หลากหลายและทีมงานดูแลตลอด 24 ชม. สามารถติดตามความเคลื่อนไหวและเข้าร่วมได้ที่ Discord ทางการครับ! [ACTION:DISCORD]",
    ],
    [
        'question' => 'ความคืบหน้าแมพ Maria City ตอนนี้เป็นอย่างไร',
        'keywords' => ['แมพ', 'ความคืบหน้า', 'map', 'เปอร์เซ็นต์', '%', 'ถึงไหน', 'คืบหน้า', 'เปิดวันไหน', 'สร้างถึงไหน'],
        'answer' => "📍 **ความคืบหน้าการพัฒนาแมพ Maria City ตอนนี้อยู่ที่ {$mapProgress}%** ครับ ทีมพัฒนากำลังเร่งตกแต่งและปรับปรุงระบบให้สมบูรณ์และเสถียรที่สุด [ACTION:MAP]",
    ],
    [
        'question' => 'สมัครเข้าเล่นเซิร์ฟเวอร์อย่างไร (Whitelist)',
        'keywords' => ['สมัคร', 'เข้าเล่น', 'เข้าร่วม', 'whitelist', 'ไวท์ลิสต์', 'สอบ', 'เข้าเซิร์ฟ'],
        'answer' => '📝 **การสมัคร Whitelist ของ Maria City Roleplay:**' . "\n" .
                    '1. เข้าร่วม Discord ทางการ: https://discord.gg/8rh4b4eu79' . "\n" .
                    '2. ไปที่ห้อง `#สมัคร-whitelist` หรือห้องสอบ' . "\n" .
                    '3. อ่านกฎเมืองและทำตามขั้นตอนที่ทีมงานระบุไว้' . "\n" .
                    'ไม่มีค่าใช้จ่ายในการสมัครครับ! [ACTION:DISCORD]',
    ],
    [
        'question' => 'Discord ทางการของ Maria City อยู่ที่ไหน',
        'keywords' => ['discord', 'ดิสคอร์ด', 'ดิส', 'ลิงก์', 'กลุ่ม'],
        'answer' => '🔗 เข้าร่วม Discord ของ Maria City ได้ที่ https://discord.gg/8rh4b4eu79 เพื่อติดตามข่าวสาร กิจกรรม และพูดคุยกับเพื่อนๆ ครับ [ACTION:DISCORD]',
    ],
    [
        'question' => 'กฎระเบียบเมือง Roleplay มีอะไรบ้าง',
        'keywords' => ['กฎ', 'กฎเมือง', 'ระเบียบ', 'roleplay', 'rp', 'rdm', 'vdm', 'fear rp', 'nlr', 'metagaming', 'powergaming'],
        'answer' => "📜 **สรุปกฎระเบียบหลักของ Maria City Roleplay:**\n" .
                    "• **RDM (Random Deathmatch):** ห้ามทำร้ายหรือสังหารผู้อื่นโดยไม่มีสตอรี่ IC รองรับ\n" .
                    "• **VDM (Vehicle Deathmatch):** ห้ามใช้ยานพาหนะไล่ชนผู้เล่นอื่นโดยเจตนา\n" .
                    "• **Fear RP:** ต้องสวมบทบาทรักตัวกลัวตายเมื่อโดนจี้หรือตกอยู่ในสถานการณ์คับขัน\n" .
                    "• **NLR (New Life Rule):** เมื่อสลบและเกิดที่โรงพยาบาล จะลืมเหตุการณ์ก่อนเสียชีวิต\n" .
                    "• **Metagaming:** ห้ามนำข้อมูลนอกเกม (OOC) มาใช้ในการเล่นในเกม (IC)\n" .
                    "• **Powergaming:** ห้ามกระทำสิ่งที่เกินจริงเหนือมนุษย์หรือบังคับบทผู้อื่น [ACTION:RULES]",
    ],
    [
        'question' => 'วิธีหาเงินและอาชีพในเมืองทำอะไรได้บ้าง',
        'keywords' => ['หาเงิน', 'ฟาร์ม', 'อาชีพ', 'งาน', 'ทำเงิน', 'รวย', 'เงินด่วน', 'ทำงาน'],
        'answer' => "💼 **ระบบอาชีพและวิธีหาเงินใน Maria City:**\n" .
                    "1. **อาชีพสุจริตทั่วไป:** เก็บผลไม้/ฟาร์มพืชผล, ขนส่งสินค้า (Trucker), ช่างซ่อมรถ (Mechanic), แท็กซี่, ตกปลา\n" .
                    "2. **อาชีพหน่วยงานรัฐ:** ตำรวจ (Police), หมอ/กู้ภัย (EMS/Medic) — มีเงินเดือนและสวัสดิการประจำ\n" .
                    "3. **พ่อค้าแม่ค้า/ธุรกิจส่วนตัว:** ซื้อขายแลกเปลี่ยนสินค้าและยานพาหนะ\n" .
                    "แนะนำสำหรับผู้เล่นใหม่: เริ่มต้นจากงานเกษตร/ฟาร์ม หรือขับรถส่งของเพื่อสะสมทุนตั้งตัวครับ!",
    ],
    [
        'question' => 'สมัครเป็นตำรวจหรือหมออย่างไร',
        'keywords' => ['ตำรวจ', 'หมอ', 'ems', 'police', 'กู้ภัย', 'หน่วยงาน', 'สมัครงาน'],
        'answer' => '👮🚑 **การสมัครหน่วยงานรัฐ (ตำรวจ / หมอ):**' . "\n" .
                    'สามารถติดตามรอบเปิดรับสมัครได้ใน Discord ของเมือง โดยจะมีการคัดกรองคุณสมบัติ การสอบข้อเขียน และการฝึกอบรมก่อนปฏิบัติหน้าที่จริงครับ [ACTION:DISCORD]',
    ],
    [
        'question' => 'แจ้งบัคหรือปัญหาอย่างไร',
        'keywords' => ['แจ้งบัค', 'บัค', 'bug', 'ปัญหา', 'error', 'ข้อผิดพลาด', 'หลุด', 'แลค'],
        'answer' => '🛠️ เลือกเมนู **“แชทกับทีมงาน”** แล้วส่งรายละเอียดพร้อมแนบภาพ/วิดีโอหลักฐานได้เลยครับ ทีมงานจะเข้ามาช่วยเหลือและตรวจสอบใน Ticket ของคุณ [ACTION:SUPPORT]',
    ],
    [
        'question' => 'แจ้งตรวจสอบผู้เล่นหรือคนใช้โปรอย่างไร',
        'keywords' => ['ตรวจสอบผู้เล่น', 'ตรวจสอบคน', 'ใช้โปร', 'โกง', 'cheat', 'hack', 'report', 'คนโกง', 'แฮก'],
        'answer' => '🚨 ส่งชื่อผู้เล่น วันเวลา สถานที่ และหลักฐานคลิปวิดีโอ/ภาพถ่าย เข้ามาที่ **“แชทกับทีมงาน”** ได้ทันทีครับ ทีมงานจะตรวจสอบอย่างเคร่งครัดและดำเนินการตามกฎ [ACTION:SUPPORT]',
    ],
    [
        'question' => 'ประวัติ ticket อยู่ที่ไหน',
        'keywords' => ['ประวัติ', 'ticket', 'ทิคเก็ต', 'แชทเก่า', 'ประวัติแชท'],
        'answer' => '📂 เปิดเมนู **“ประวัติแชท”** ที่แถบเมนูด้านซ้าย เพื่อดู Ticket เก่าและประวัติการสนทนาทั้งหมดของคุณได้ครับ',
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

echo json_encode([
    'information' => $information,
    'frequent_questions' => $frequentQuestions,
    'map_progress' => $mapProgress,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
