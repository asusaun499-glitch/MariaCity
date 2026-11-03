<?php
ini_set('display_errors', '0');
error_reporting(0);
ob_start();

require_once 'config.php';

try {
    $action = $_GET['action'] ?? '';

    // ==================== MAP PROGRESS ====================
    if ($action === 'get_map') {
        $mapFile = DATA_DIR . '/map.json';
        $data = file_exists($mapFile) ? (json_decode(@file_get_contents($mapFile), true) ?: ['progress' => 0]) : ['progress' => 0];
        ob_end_clean();
        jsonResponse($data);
    }

    if ($action === 'update_map' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $progress = max(0, min(100, (int)($input['progress'] ?? 0)));
        @file_put_contents(DATA_DIR . '/map.json', json_encode(['progress' => $progress]));
        ob_end_clean();
        jsonResponse(['success' => true, 'progress' => $progress]);
    }

    // ==================== CHAT LOGS ====================
    if ($action === 'add_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Not logged in'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', currentUser()['uid']);
        $logFile = DATA_DIR . "/logs_{$uid}.json";
        $logs = file_exists($logFile) ? (json_decode(@file_get_contents($logFile), true) ?: []) : [];
        $logs[] = [
            'sender'    => in_array($input['sender'] ?? '', ['user','bot']) ? $input['sender'] : 'user',
            'text'      => substr($input['text'] ?? '', 0, 2000),
            'type'      => 'text',
            'timestamp' => time() * 1000,
        ];
        // 14-Day TTL
        $ttl = 14 * 86400 * 1000;
        $now = time() * 1000;
        $logs = array_values(array_filter($logs, fn($l) => ($now - ($l['timestamp'] ?? 0)) <= $ttl));
        @file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE));
        ob_end_clean();
        jsonResponse(['success' => true]);
    }

    if ($action === 'get_logs') {
        if (!isLoggedIn()) { ob_end_clean(); echo json_encode([]); exit; }
        $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', currentUser()['uid']);
        $logFile = DATA_DIR . "/logs_{$uid}.json";
        $logs = file_exists($logFile) ? (json_decode(@file_get_contents($logFile), true) ?: []) : [];
        ob_end_clean();
        jsonResponse($logs);
    }

    if ($action === 'sign_ai_knowledge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $question = trim(substr((string)($input['question'] ?? ''), 0, 300));
        $answer = trim(substr((string)($input['answer'] ?? ''), 0, 1500));
        $keywords = array_slice(array_values(array_unique(array_filter(array_map(
            fn($word) => trim(substr((string)$word, 0, 80)),
            is_array($input['keywords'] ?? null) ? $input['keywords'] : []
        )))), 0, 20);
        if ($question === '' || $answer === '' || !$keywords) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'ข้อมูลความรู้ไม่ครบ'], 400); }
        $entry = ['question' => $question, 'keywords' => $keywords, 'answer' => $answer];
        $signature = hash_hmac('sha256', json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), APP_SECRET);
        ob_end_clean(); jsonResponse(['success' => true, 'signature' => $signature, 'entry' => $entry]);
    }

    if ($action === 'ai_reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = currentUser() ?: [
            'uid' => 'u_' . substr(md5($_SERVER['REMOTE_ADDR'] ?? 'guest'), 0, 10),
            'name' => 'ผู้เล่น',
            'email' => '',
            'role' => 'user'
        ];

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $question = trim(substr((string)($input['question'] ?? ''), 0, 1500));
        if ($question === '') { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'กรุณาพิมพ์คำถาม'], 400); }

        // ดึงความคืบหน้าแมพปัจจุบัน
        $mapFile = DATA_DIR . '/map.json';
        $mapData = file_exists($mapFile) ? (json_decode(@file_get_contents($mapFile), true) ?: []) : [];
        $currentProgress = max(0, min(100, (int)($mapData['progress'] ?? 0)));

        // รวบรวมข้อมูลความรู้พื้นฐานและจากหลังบ้าน
        $information = [
            [
                'question' => 'ความคืบหน้าแมพ Maria City',
                'keywords' => ['แมพ', 'ความคืบหน้า', 'map', 'เปอร์เซ็นต์', '%', 'ถึงไหน', 'คืบหน้า', 'เปิดวันไหน', 'สร้างถึงไหน'],
                'answer' => "📍 **ความคืบหน้าการพัฒนาแมพ Maria City ตอนนี้อยู่ที่ {$currentProgress}%** ครับ ทีมพัฒนากำลังเร่งตกแต่งและปรับปรุงระบบให้สมบูรณ์และเสถียรที่สุด [ACTION:MAP]"
            ],
            [
                'question' => 'สมัคร Whitelist',
                'keywords' => ['สมัคร', 'whitelist', 'ไวท์ลิสต์', 'เข้าเล่น', 'เข้าเซิร์ฟ', 'สอบ'],
                'answer' => "📝 **การสมัคร Whitelist ของ Maria City Roleplay:**\n1. เข้าร่วม Discord ทางการ: https://discord.gg/8rh4b4eu79\n2. ไปที่ห้อง `#สมัคร-whitelist` หรือห้องสอบ\n3. อ่านกฎเมืองและทำตามขั้นตอนที่ทีมงานระบุไว้\nไม่มีค่าใช้จ่ายในการสมัครครับ! [ACTION:DISCORD]"
            ],
            [
                'question' => 'Discord ทางการ',
                'keywords' => ['discord', 'ดิสคอร์ด', 'ดิส', 'ลิงก์', 'กลุ่ม'],
                'answer' => "🔗 เข้าร่วม Discord ของ Maria City ได้ที่ https://discord.gg/8rh4b4eu79 เพื่อติดตามข่าวสาร กิจกรรม และพูดคุยกับเพื่อนๆ ครับ [ACTION:DISCORD]"
            ],
            [
                'question' => 'แจ้งปัญหาหรือบัค',
                'keywords' => ['บัค', 'bug', 'ปัญหา', 'แจ้งปัญหา', 'error', 'หลุด', 'แลค', 'crash'],
                'answer' => "🛠️ เปิดเมนู **“แชทกับทีมงาน”** เพื่อส่งรายละเอียดพร้อมแนบภาพ/วิดีโอหลักฐานได้เลยครับ ทีมงานจะตอบกลับใน Ticket [ACTION:SUPPORT]"
            ],
            [
                'question' => 'รายงานผู้เล่นหรือคนใช้โปร',
                'keywords' => ['รายงานผู้เล่น', 'ตรวจสอบผู้เล่น', 'โกง', 'ใช้โปร', 'แฮก', 'hack', 'cheat', 'report'],
                'answer' => "🚨 ส่งชื่อผู้เล่น วันเวลา สถานที่ และหลักฐานคลิปวิดีโอ/ภาพถ่าย เข้ามาที่ **“แชทกับทีมงาน”** ได้ทันทีครับ [ACTION:SUPPORT]"
            ],
            [
                'question' => 'กฎระเบียบเมือง Roleplay',
                'keywords' => ['กฎ', 'กฎเมือง', 'ระเบียบ', 'roleplay', 'rp', 'rdm', 'vdm', 'fear rp', 'nlr', 'metagaming', 'powergaming'],
                'answer' => "📜 **สรุปกฎระเบียบหลักของ Maria City Roleplay:**\n• **RDM:** ห้ามทำร้าย/สังหารผู้อื่นโดยไม่มีสตอรี่ IC\n• **VDM:** ห้ามใช้ยานพาหนะชนผู้เล่นอื่นโดยเจตนา\n• **Fear RP:** ต้องสวมบทบาทกลัวตายเมื่ออยู่ในสถานการณ์อันตราย\n• **NLR:** เมื่อสลบเกิดใหม่ที่โรงพยาบาล จะลืมเหตุการณ์ก่อนเสียชีวิต\n• **Metagaming:** ห้ามนำข้อมูลนอกเกม (OOC) มาใช้ในเกม (IC)\n• **Powergaming:** ห้ามกระทำสิ่งที่เกินจริงเหนือมนุษย์ [ACTION:RULES]"
            ],
            [
                'question' => 'วิธีหาเงินและอาชีพในเมือง',
                'keywords' => ['หาเงิน', 'ฟาร์ม', 'อาชีพ', 'งาน', 'ทำเงิน', 'รวย', 'เงินด่วน', 'ทำงาน'],
                'answer' => "💼 **ระบบอาชีพและวิธีหาเงินใน Maria City:**\n1. **อาชีพสุจริตทั่วไป:** เก็บผลไม้/ฟาร์มพืชผล, ขนส่งสินค้า (Trucker), ช่างซ่อมรถ (Mechanic), แท็กซี่, ตกปลา\n2. **อาชีพหน่วยงานรัฐ:** ตำรวจ (Police), หมอ/กู้ภัย (EMS/Medic) มีเงินเดือนและสวัสดิการประจำ\n3. **ธุรกิจส่วนตัว:** ค้าขายแลกเปลี่ยนสินค้าและยานพาหนะ\nสำหรับผู้เล่นใหม่ แนะนำเริ่มต้นจากการฟาร์มพืชผลหรือขับรถส่งของเพื่อสะสมทุนครับ!"
            ],
            [
                'question' => 'การสมัครตำรวจหรือหมอ',
                'keywords' => ['ตำรวจ', 'หมอ', 'ems', 'police', 'กู้ภัย', 'หน่วยงาน', 'สมัครงาน'],
                'answer' => "👮🚑 **การสมัครหน่วยงานรัฐ (ตำรวจ / หมอ):**\nติดตามรอบเปิดรับสมัครได้ใน Discord ของเมือง โดยจะมีการคัดกรองคุณสมบัติ การสอบข้อเขียน และการฝึกอบรมก่อนปฏิบัติหน้าที่จริงครับ [ACTION:DISCORD]"
            ],
            [
                'question' => 'ประวัติการสนทนา',
                'keywords' => ['ประวัติแชท', 'ประวัติ ticket', 'ประวัติ'],
                'answer' => "📂 เปิดเมนู **“ประวัติแชท”** ที่แถบเมนูด้านซ้าย เพื่อดูบทสนทนาและ Ticket เดิมได้ตลอด 14 วันครับ"
            ],
        ];

        // โหลดข้อมูลที่ Admin อนุมัติไว้
        $approved = json_decode(@file_get_contents(DATA_DIR . '/ai_knowledge.json'), true) ?: [];
        foreach ($approved as $entry) {
            if (!empty($entry['question']) && !empty($entry['answer']) && is_array($entry['keywords'] ?? null)) {
                $information[] = ['source' => 'admin-approved', 'question' => $entry['question'], 'keywords' => $entry['keywords'], 'answer' => $entry['answer']];
            }
        }

        // โหลดข้อมูลที่ส่งมาจาก Client และมีการเซ็นชื่อกำกับ
        $clientKnowledge = is_array($input['knowledge'] ?? null) ? array_slice($input['knowledge'], 0, 40) : [];
        foreach ($clientKnowledge as $entry) {
            if (is_array($entry) && !empty($entry['question']) && !empty($entry['answer']) && is_array($entry['keywords'] ?? null)) {
                $signedEntry = [
                    'question' => substr((string)$entry['question'], 0, 300),
                    'keywords' => array_slice(array_map(fn($word) => substr((string)$word, 0, 80), $entry['keywords']), 0, 20),
                    'answer' => substr((string)$entry['answer'], 0, 1500),
                ];
                $expectedSignature = hash_hmac('sha256', json_encode($signedEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), APP_SECRET);
                if (isset($entry['signature']) && hash_equals($expectedSignature, (string)$entry['signature'])) {
                    $signedEntry['source'] = 'admin-approved';
                    $information[] = $signedEntry;
                }
            }
        }

        // ฟังก์ชันระบบตอบคำถามอัจฉริยะ (Smart Fallback Engine) กรณี Gemini ออฟไลน์หรือยังไม่ตั้ง Key
        $smartEngineReply = function(string $q) use ($currentProgress, $information, $user): array {
            $normalized = mb_strtolower(preg_replace('/[\p{P}\p{S}\s]+/u', '', $q), 'UTF-8');

            // 1. ความคืบหน้าแมพ
            if (preg_match('/(แมพ|map|เปอร์เซ็น|ความคืบหน้า|ถึงไหน|สร้างถึง|เปิดวันไหน|เปิดตอนไหน)/u', $q)) {
                return [
                    'answer' => "📍 **ความคืบหน้าการพัฒนาแมพ Maria City ตอนนี้อยู่ที่ {$currentProgress}%** ครับ!\nทีมพัฒนากำลังเร่งตกแต่งโซนและทดสอบระบบต่างๆ ให้มีความเสถียรที่สุด ผู้เล่นสามารถติดตามภาพพรีวิวและประกาศอัปเดตล่าสุดได้ที่ Discord ของเมืองครับ [ACTION:MAP] [ACTION:DISCORD]",
                    'needs_info' => false
                ];
            }

            // 2. สมัคร Whitelist / เข้าเล่น
            if (preg_match('/(สมัคร|whitelist|ไวท์ลิส|เข้าเล่น|เข้าเซิร์ฟ|เล่นยังไง|เริ่มต้น)/u', $q)) {
                return [
                    'answer' => "📝 **ขั้นตอนการสมัคร Whitelist เข้าสู่ Maria City:**\n1. เข้าร่วม Discord ทางการ: https://discord.gg/8rh4b4eu79\n2. เข้าไปที่ห้อง `#สมัคร-whitelist`\n3. ศึกษากฎระเบียบของเมือง และทำตามคำแนะนำของแอดมิน\nการสมัครเข้าเล่นไม่มีค่าใช้จ่ายใดๆ ครับ มาร่วมสร้างสตอรี่ดีๆ ด้วยกันนะครับ! [ACTION:DISCORD]",
                    'needs_info' => false
                ];
            }

            // 3. Discord
            if (preg_match('/(discord|ดิสคอร์ด|ดิส|ลิงก์ดิส)/u', $q)) {
                return [
                    'answer' => "🔗 **Discord ทางการของ Maria City Roleplay:**\n👉 https://discord.gg/8rh4b4eu79\nเข้ามาร่วมพูดคุย แจ้งปัญหา และรับข่าวสารกิจกรรมพิเศษได้เลยครับ! [ACTION:DISCORD]",
                    'needs_info' => false
                ];
            }

            // 4. แจ้งปัญหา / บัค / ตรวจสอบผู้เล่น / โปร
            if (preg_match('/(แจ้งบัค|บัค|bug|ปัญหา|error|หลุด|แลค|โปร|แฮก|hack|cheat|โกง|คนโกง|ช่วยด้วย)/u', $q)) {
                return [
                    'answer' => "🛠️ หากพบเจอบัค ปัญหาในเกม หรือต้องการแจ้งตรวจสอบผู้เล่น:\nท่านสามารถคลิกเมนู **“แชทกับทีมงาน”** ที่แถบเมนูด้านซ้าย แล้วส่งรายละเอียดพร้อมภาพ/วิดีโอหลักฐานได้ทันที ทีมงานจะเร่งตรวจสอบให้ครับ [ACTION:SUPPORT]",
                    'needs_info' => false
                ];
            }

            // 5. กฎระเบียบเมือง
            if (preg_match('/(กฎ|ระเบียบ|rdm|vdm|fear|nlr|meta|powergaming|roleplay|กฎเมือง)/u', $q)) {
                return [
                    'answer' => "📜 **กฎระเบียบหลักของ Maria City Roleplay ที่ต้องทราบ:**\n• **RDM (Random Deathmatch):** ห้ามทำร้ายหรือสังหารผู้อื่นโดยไม่มีสตอรี่ IC รองรับ\n• **VDM (Vehicle Deathmatch):** ห้ามใช้ยานพาหนะเป็นอาวุธไล่ชนผู้เล่นอื่น\n• **Fear RP:** ต้องสวมบทบาทเกรงกลัวอันตรายและเคารพชีวิตตัวละคร\n• **NLR (New Life Rule):** เมื่อสลบและเกิดที่โรงพยาบาล จะลืมเรื่องราวก่อนเสียชีวิตทั้งหมด\n• **Metagaming:** ห้ามนำข้อมูลนอกเกม (OOC) มาใช้ในการเล่นในเกม (IC)\n• **Powergaming:** ห้ามกระทำสิ่งที่เกินจริงเหนือมนุษย์ [ACTION:RULES]",
                    'needs_info' => false
                ];
            }

            // 6. วิธีหาเงิน / อาชีพ / ทำงาน
            if (preg_match('/(หาเงิน|ฟาร์ม|อาชีพ|งาน|ทำเงิน|รวย|เงินด่วน|ทำงาน)/u', $q)) {
                return [
                    'answer' => "💼 **ระบบอาชีพและการสร้างรายได้ใน Maria City:**\n1. **อาชีพทั่วไป:** เก็บผลไม้/ฟาร์มพืชผล, ขนส่งสินค้า (Trucker), ช่างยนต์ (Mechanic), แท็กซี่, ตกปลา\n2. **อาชีพหน่วยงานรัฐ:** ตำรวจ (Police) และแพทย์ (EMS/Medic) มีเงินเดือนและสิทธิประโยชน์ประจำ\n3. **การค้าขาย:** เป็นพ่อค้าแม่ค้าอิสระ เปิดตลาดนัด หรือซื้อขายรถยนต์\n💡 *คำแนะนำสำหรับผู้เล่นใหม่:* เริ่มจากงานเก็บผลไม้หรือขับรถส่งของเพื่อสะสมเงินทุนตั้งตัวได้ไวที่สุดครับ!",
                    'needs_info' => false
                ];
            }

            // 7. ตำรวจ / หมอ
            if (preg_match('/(ตำรวจ|หมอ|ems|police|กู้ภัย|หน่วยงาน)/u', $q)) {
                return [
                    'answer' => "👮🚑 **การสมัครเป็นตำรวจหรือหน่วยกู้ภัย/แพทย์ (EMS):**\nหน่วยงานรัฐจะเปิดรับสมัครเป็นรอบๆ ผ่านประกาศใน Discord ของเมือง โดยจะมีการสอบสัมภาษณ์ ตรวจสอบประวัติ และฝึกอบรมก่อนเริ่มงานครับ [ACTION:DISCORD]",
                    'needs_info' => false
                ];
            }

            // 8. ทักทาย
            if (preg_match('/(สวัสดี|หวัดดี|ดีจ้า|hello|hi|เฮลโล|มารี|น้องมารี|mariatown|maria town)/u', $q)) {
                return [
                    'answer' => "👋 สวัสดีค่ะ! น้องมารี ผู้ช่วย AI อัจฉริยะประจำ **Maria Town (Maria City Roleplay)** ยินดีต้อนรับค่ะ\nวันนี้มีอะไรให้น้องมารีช่วยเหลือไหมคะ? ถามเกี่ยวกับ ความคืบหน้าแมพ, วิธีสมัคร Whitelist, แนะนำอาชีพหาเงิน, หรือกฎระเบียบเมือง ได้เลยนะคะ! 😊",
                    'needs_info' => false
                ];
            }

            // 9. ค้นหาจากฐานความรู้ (Keyword Match)
            $bestMatch = null;
            $bestScore = 0;
            foreach ($information as $item) {
                $score = 0;
                foreach ($item['keywords'] ?? [] as $kw) {
                    $kwClean = mb_strtolower(preg_replace('/[\p{P}\p{S}\s]+/u', '', (string)$kw), 'UTF-8');
                    if ($kwClean !== '' && str_contains($normalized, $kwClean)) {
                        $score += max(2, mb_strlen($kwClean, 'UTF-8'));
                    }
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $item;
                }
            }

            if ($bestScore >= 3 && $bestMatch) {
                return [
                    'answer' => $bestMatch['answer'],
                    'needs_info' => false
                ];
            }

            // 10. ถ้าไม่พบข้อมูล บันทึกคำถามเพื่อให้ Admin เพิ่มในฐานความรู้
            try {
                $qFile = DATA_DIR . '/ai_questions.json';
                $questionsList = json_decode(@file_get_contents($qFile), true) ?: [];
                $qFound = false;
                foreach ($questionsList as &$qi) {
                    $existingNorm = mb_strtolower(preg_replace('/[\p{P}\p{S}\s]+/u', '', (string)($qi['question'] ?? '')), 'UTF-8');
                    if ($existingNorm === $normalized) {
                        $qi['count'] = (int)($qi['count'] ?? 1) + 1;
                        $qi['last_seen'] = time();
                        $qFound = true;
                        break;
                    }
                }
                unset($qi);
                if (!$qFound) {
                    $questionsList[] = [
                        'id' => bin2hex(random_bytes(8)),
                        'question' => $q,
                        'count' => 1,
                        'status' => 'pending',
                        'first_seen' => time(),
                        'last_seen' => time(),
                        'user_name' => substr((string)($user['name'] ?? 'ผู้ใช้'), 0, 100),
                    ];
                }
                @file_put_contents($qFile, json_encode($questionsList, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            } catch (Throwable $e) {}

            return [
                'answer' => "น้องมารียังไม่มีข้อมูลที่แน่ชัดสำหรับคำถามนี้ค่ะ ได้ทำการบันทึกหัวข้อนี้ส่งให้ทีมงานตรวจสอบแล้วนะคะ\n\nหากเป็นเรื่องด่วนหรือปัญหาในเกม สามารถติดต่อพูดคุยกับทีมงานได้ที่เมนู **“แชทกับทีมงาน”** หรือสอบถามเพื่อนๆ ใน Discord ได้เลยค่ะ! [ACTION:SUPPORT] [ACTION:DISCORD]",
                'needs_info' => true
            ];
        };

        // ดึงค่า API Key สำหรับ Gemini
        $apiKey = getenv('GEMINI_API_KEY');
        if (!$apiKey && isset($_ENV['GEMINI_API_KEY'])) $apiKey = $_ENV['GEMINI_API_KEY'];
        if (!$apiKey && isset($_SERVER['GEMINI_API_KEY'])) $apiKey = $_SERVER['GEMINI_API_KEY'];
        $apiKey = trim((string)$apiKey);

        // หากยังไม่ได้ตั้งค่า API Key ให้ใช้ Smart Fallback Engine ตอบทันที
        if ($apiKey === '') {
            $result = $smartEngineReply($question);
            ob_end_clean();
            jsonResponse([
                'success' => true,
                'answer' => $result['answer'],
                'needs_info' => $result['needs_info'],
                'source' => 'smart_engine'
            ]);
        }

        // จัดเตรียมข้อความประวัติการสนทนา
        $history = is_array($input['history'] ?? null) ? array_slice($input['history'], -8) : [];
        if ($history && ($history[count($history) - 1]['role'] ?? '') === 'user'
            && trim((string)($history[count($history) - 1]['text'] ?? '')) === $question) {
            array_pop($history);
        }
        $contents = [];
        foreach ($history as $message) {
            $role = ($message['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $text = trim(substr((string)($message['text'] ?? ''), 0, 1000));
            if ($text !== '') $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $question]]];

        // สร้าง System Instruction ที่ฉลาด ครอบคลุม และสุภาพ
        $systemText = "คุณคือน้องมารี ผู้ช่วย AI อัจฉริยะประจำเมือง Maria Town (Maria City Roleplay) "
            . "ตอบภาษาไทยด้วยน้ำเสียงที่สุภาพ อบอุ่น เป็นมิตร น่ารักและเป็นธรรมชาติ มีหางเสียง (คะ/ค่ะ/นะคะ หรือ ครับ) "
            . "ใช้ Markdown ในการจัดระเบียบข้อความให้อ่านง่าย เช่น ใช้หัวข้อ ตัวหนาเน้นคำสำคัญ และ bullet points พร้อมอิโมจิประกอบ\n\n"
            . "ข้อมูลสำคัญของเมือง Maria City:\n"
            . "- ความคืบหน้าการพัฒนาแมพปัจจุบันคือ {$currentProgress}% หากผู้ใช้ถามเรื่องความคืบหน้าแมพ ให้ตอบสถานะนี้ทันที\n"
            . "- การสมัคร Whitelist: สมัครได้ฟรีที่ Discord: https://discord.gg/8rh4b4eu79 ในห้อง #สมัคร-whitelist\n"
            . "- Discord ทางการ: https://discord.gg/8rh4b4eu79\n"
            . "- การแจ้งปัญหา/แจ้งบัค/ตรวจสอบผู้เล่น: แนะนำให้ใช้เมนู 'แชทกับทีมงาน'\n"
            . "- กฎระเบียบเมือง: RDM, VDM, Fear RP, NLR, Metagaming, Powergaming\n"
            . "- ระบบอาชีพและวิธีหาเงิน: งานสุจริต (ฟาร์ม, ส่งของ, ช่าง, แท็กซี่, ตกปลา), หน่วยงานรัฐ (ตำรวจ, หมอ/EMS)\n\n"
            . "แท็ก Action เสริม (ใส่ต่อท้ายคำตอบเมื่อหัวข้อเกี่ยวข้องเพื่อให้ UI สร้างปุ่มคลิกได้):\n"
            . "- ถ้าเกี่ยวกับแจ้งปัญหา/บัค/ติดต่อทีมงาน ให้ใส่ [ACTION:SUPPORT]\n"
            . "- ถ้าเกี่ยวกับความคืบหน้าแมพ ให้ใส่ [ACTION:MAP]\n"
            . "- ถ้าเกี่ยวกับ Discord หรือสมัคร Whitelist ให้ใส่ [ACTION:DISCORD]\n"
            . "- ถ้าเกี่ยวกับกฎระเบียบเมือง ให้ใส่ [ACTION:RULES]\n\n"
            . "ฐานความรู้เพิ่มเติม:\n" . json_encode($information, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $payload = [
            'system_instruction' => ['parts' => [['text' => $systemText]]],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.25,
                'maxOutputTokens' => 600,
                'topP' => 0.95
            ],
        ];

        // กำหนดโมเดล Gemini ล่าสุด
        $modelsToTry = [];
        $envModel = getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? ($_SERVER['GEMINI_MODEL'] ?? ''));
        if ($envModel) $modelsToTry[] = $envModel;
        $modelsToTry[] = 'gemini-2.5-flash';
        $modelsToTry[] = 'gemini-2.0-flash';
        $modelsToTry[] = 'gemini-1.5-flash';
        $modelsToTry[] = 'gemini-2.0-flash-lite';
        $modelsToTry[] = 'gemini-1.5-pro';
        $modelsToTry = array_unique(array_filter($modelsToTry));

        $responseBody = false;
        $httpCode = 500;
        $apiErrorMsg = '';
        $usedModel = '';

        foreach ($modelsToTry as $model) {
            if (!preg_match('/^[a-zA-Z0-9._-]+$/', $model)) continue;
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
            ]);
            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($responseBody !== false && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($responseBody, true) ?: [];
                if (!isset($decoded['error']) && !empty($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                    $usedModel = $model;
                    break;
                } else {
                    $apiErrorMsg = $decoded['error']['message'] ?? '';
                }
            } else {
                $decoded = json_decode($responseBody, true) ?: [];
                $apiErrorMsg = $decoded['error']['message'] ?? $curlError;
            }
        }

        // หาก Gemini สำเร็จ ให้ดึงคำตอบมาแสดง
        if ($responseBody !== false && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($responseBody, true) ?: [];
            $answer = trim((string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
            if ($answer !== '') {
                $needsInfo = str_starts_with($answer, 'NEED_INFO:');
                if ($needsInfo) $answer = trim(substr($answer, strlen('NEED_INFO:')));
                ob_end_clean();
                jsonResponse([
                    'success' => true,
                    'answer' => $answer,
                    'needs_info' => $needsInfo,
                    'source' => 'gemini',
                    'model' => $usedModel
                ]);
            }
        }

        // กรณี Gemini ไม่ตอบกลับ ให้ใช้ Smart Fallback Engine ตอบแทนทันที (Zero Downtime)
        error_log('Gemini request fallback triggered: ' . $apiErrorMsg);
        $fallback = $smartEngineReply($question);
        ob_end_clean();
        jsonResponse([
            'success' => true,
            'answer' => $fallback['answer'],
            'needs_info' => $fallback['needs_info'],
            'source' => 'smart_engine_fallback'
        ]);
    }

    if ($action === 'clear_logs') {
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['success' => false], 401); }
        $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', currentUser()['uid']);
        $logFile = DATA_DIR . "/logs_{$uid}.json";
        if (file_exists($logFile)) @unlink($logFile);
        ob_end_clean();
        jsonResponse(['success' => true]);
    }

    if ($action === 'all_logs') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $allLogs = [];
        foreach (glob(DATA_DIR . '/logs_*.json') ?: [] as $file) {
            $fileLogs = json_decode(@file_get_contents($file), true) ?: [];
            foreach ($fileLogs as $log) { $allLogs[] = $log; }
        }
        usort($allLogs, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
        ob_end_clean();
        jsonResponse(['logs' => array_slice($allLogs, 0, 500)]);
    }

    // ==================== AI HISTORY AND KNOWLEDGE ====================
    if ($action === 'save_ai_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $user = currentUser() ?: [
            'uid' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($input['uid'] ?? 'u_guest')),
            'name' => substr((string)($input['name'] ?? 'ผู้ใช้'), 0, 100),
            'email' => substr((string)($input['email'] ?? ''), 0, 200)
        ];
        $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $user['uid'] ?? '');
        $role = in_array($input['role'] ?? '', ['user', 'assistant'], true) ? $input['role'] : '';
        $text = trim(substr((string)($input['text'] ?? ''), 0, 2000));
        $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($input['session_id'] ?? ''));
        if (!$uid || !$role || !$text || !$sessionId) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Invalid message'], 400); }
        $file = DATA_DIR . "/ai_logs_{$uid}.json";
        $messages = json_decode(@file_get_contents($file), true) ?: [];
        $cutoff = (time() - 14 * 86400) * 1000;
        $messages = array_values(array_filter($messages, fn($item) => (int)($item['timestamp'] ?? 0) >= $cutoff));
        $messages[] = [
            'uid' => $uid,
            'name' => substr((string)($user['name'] ?? 'ผู้ใช้'), 0, 100),
            'email' => substr((string)($user['email'] ?? ''), 0, 200),
            'session_id' => $sessionId,
            'role' => $role,
            'text' => $text,
            'timestamp' => time() * 1000,
        ];
        if (count($messages) > 1000) $messages = array_slice($messages, -1000);
        @file_put_contents($file, json_encode($messages, JSON_UNESCAPED_UNICODE), LOCK_EX);
        ob_end_clean(); jsonResponse(['success' => true]);
    }

    if ($action === 'my_ai_history') {
        $user = currentUser();
        $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $user['uid'] ?? ($_GET['uid'] ?? ''));
        if (!$uid) { ob_end_clean(); jsonResponse(['messages' => []]); }
        $file = DATA_DIR . "/ai_logs_{$uid}.json";
        $messages = json_decode(@file_get_contents($file), true) ?: [];
        $cutoff = (time() - 14 * 86400) * 1000;
        $messages = array_values(array_filter($messages, fn($item) => (int)($item['timestamp'] ?? 0) >= $cutoff));
        @file_put_contents($file, json_encode($messages, JSON_UNESCAPED_UNICODE), LOCK_EX);
        ob_end_clean(); jsonResponse(['messages' => $messages]);
    }

    if ($action === 'admin_ai_history') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $cutoff = (time() - 14 * 86400) * 1000;
        $messages = [];
        foreach (glob(DATA_DIR . '/ai_logs_*.json') ?: [] as $file) {
            $items = json_decode(@file_get_contents($file), true) ?: [];
            $items = array_values(array_filter($items, fn($item) => (int)($item['timestamp'] ?? 0) >= $cutoff));
            @file_put_contents($file, json_encode($items, JSON_UNESCAPED_UNICODE), LOCK_EX);
            foreach ($items as $item) $messages[] = $item;
        }
        usort($messages, fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));
        ob_end_clean(); jsonResponse(['messages' => array_slice($messages, 0, 3000)]);
    }

    if ($action === 'submit_ai_question' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Not logged in'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $question = trim(substr((string)($input['question'] ?? ''), 0, 500));
        $suggestion = trim(substr((string)($input['suggestion'] ?? ''), 0, 1200));
        if ($question === '') { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Question is required'], 400); }
        $user = currentUser();
        $file = DATA_DIR . '/ai_questions.json';
        $questions = json_decode(@file_get_contents($file), true) ?: [];
        $cutoff = time() - 14 * 86400;
        $questions = array_values(array_filter($questions, fn($item) => (int)($item['last_seen'] ?? 0) >= $cutoff));
        $normalized = mb_strtolower(preg_replace('/[\p{P}\p{S}\s]+/u', '', $question), 'UTF-8');
        $found = false;
        foreach ($questions as &$item) {
            $existing = mb_strtolower(preg_replace('/[\p{P}\p{S}\s]+/u', '', (string)($item['question'] ?? '')), 'UTF-8');
            if ($existing === $normalized) {
                $item['count'] = (int)($item['count'] ?? 1) + 1;
                $item['last_seen'] = time();
                if ($suggestion !== '') {
                    $item['suggestions'] = $item['suggestions'] ?? [];
                    $item['suggestions'][] = ['text' => $suggestion, 'user_name' => substr((string)($user['name'] ?? 'ผู้ใช้'), 0, 100), 'timestamp' => time()];
                    $item['suggestions'] = array_slice($item['suggestions'], -20);
                }
                if (($item['status'] ?? '') !== 'approved') $item['status'] = 'pending';
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $questions[] = [
                'id' => bin2hex(random_bytes(8)), 'question' => $question, 'suggestion' => $suggestion,
                'count' => 1, 'status' => 'pending', 'first_seen' => time(), 'last_seen' => time(),
                'user_name' => substr((string)($user['name'] ?? 'ผู้ใช้'), 0, 100),
                'suggestions' => $suggestion !== '' ? [['text' => $suggestion, 'user_name' => substr((string)($user['name'] ?? 'ผู้ใช้'), 0, 100), 'timestamp' => time()]] : [],
            ];
        }
        @file_put_contents($file, json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        ob_end_clean(); jsonResponse(['success' => true]);
    }

    if ($action === 'admin_ai_questions') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $file = DATA_DIR . '/ai_questions.json';
        $questions = json_decode(@file_get_contents($file), true) ?: [];
        $cutoff = time() - 14 * 86400;
        $questions = array_values(array_filter($questions, fn($item) => (int)($item['last_seen'] ?? 0) >= $cutoff));
        @file_put_contents($file, json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        usort($questions, fn($a, $b) => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));
        ob_end_clean(); jsonResponse(['questions' => $questions]);
    }

    if ($action === 'approve_ai_answer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $questionId = preg_replace('/[^a-f0-9]/', '', (string)($input['id'] ?? ''));
        $answer = trim(substr((string)($input['answer'] ?? ''), 0, 2000));
        $keywords = array_values(array_filter(array_map(fn($word) => trim(substr((string)$word, 0, 80)), $input['keywords'] ?? [])));
        if (!$questionId || !$answer) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Question and answer are required'], 400); }
        $questionFile = DATA_DIR . '/ai_questions.json';
        $questions = json_decode(@file_get_contents($questionFile), true) ?: [];
        $found = false;
        foreach ($questions as &$item) {
            if (($item['id'] ?? '') !== $questionId) continue;
            $item['status'] = 'approved';
            $item['answer'] = $answer;
            $item['reviewed_at'] = time();
            $found = true;
            $knowledgeFile = DATA_DIR . '/ai_knowledge.json';
            $knowledge = json_decode(@file_get_contents($knowledgeFile), true) ?: [];
            $knowledge = array_values(array_filter($knowledge, fn($entry) => ($entry['question_id'] ?? '') !== $questionId));
            $keywords[] = $item['question'];
            $knowledge[] = ['question_id' => $questionId, 'question' => $item['question'], 'keywords' => array_values(array_unique($keywords)), 'answer' => $answer];
            @file_put_contents($knowledgeFile, json_encode($knowledge, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            break;
        }
        unset($item);
        if (!$found) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Question not found'], 404); }
        @file_put_contents($questionFile, json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        ob_end_clean(); jsonResponse(['success' => true]);
    }

    // ==================== TICKETS ====================
    if ($action === 'create_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Not logged in'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $user = currentUser();
        $tickets = loadTickets();
        $newTicket = [
            'id'          => 'TKT-' . str_pad(count($tickets) + 1, 3, '0', STR_PAD_LEFT),
            'type'        => in_array($input['type'] ?? '', ['check_player','report_bug','general']) ? $input['type'] : 'general',
            'status'      => 'pending',
            'from_uid'    => $user['uid'],
            'from_name'   => $user['name'],
            'from_email'  => $user['email'],
            'message'     => substr($input['message'] ?? '', 0, 1000),
            'evidence'    => [],
            'admin_reply' => null,
            'messages'    => [[
                'role' => 'user',
                'sender_name' => $user['name'],
                'text' => substr($input['message'] ?? '', 0, 1000),
                'image' => '',
                'ts' => time() * 1000,
            ]],
            'created_at'  => time(),
            'updated_at'  => time(),
        ];
        array_unshift($tickets, $newTicket);
        saveTickets($tickets);
        ob_end_clean();
        jsonResponse(['success' => true, 'ticket_id' => $newTicket['id']]);
    }

    if ($action === 'my_tickets') {
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['tickets' => []]); }
        $user = currentUser();
        $tickets = array_values(array_filter(loadTickets(), fn($t) => $t['from_uid'] === $user['uid']));
        ob_end_clean();
        jsonResponse(['tickets' => $tickets]);
    }

    if ($action === 'all_tickets') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        ob_end_clean();
        jsonResponse(['tickets' => loadTickets()]);
    }

    if ($action === 'reply_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ticketId = $input['ticket_id'] ?? '';
        $reply = substr($input['reply'] ?? '', 0, 2000);
        $tickets = loadTickets();
        $found = false;
        foreach ($tickets as &$t) {
            if ($t['id'] === $ticketId) {
                if (empty($t['messages'])) {
                    $t['messages'] = [['role' => 'user', 'sender_name' => $t['from_name'] ?? 'ผู้เล่น', 'text' => $t['message'] ?? '', 'image' => '', 'ts' => ((int)($t['created_at'] ?? time())) * 1000]];
                    if (!empty($t['admin_reply'])) $t['messages'][] = ['role' => 'admin', 'sender_name' => 'ทีมงาน', 'text' => $t['admin_reply'], 'image' => '', 'ts' => ((int)($t['updated_at'] ?? time())) * 1000];
                }
                $t['admin_reply'] = $reply;
                $t['status']      = 'replied';
                $t['updated_at']  = time();
                $t['messages'][] = [
                    'role' => 'admin', 'sender_name' => currentUser()['name'] ?? 'ทีมงาน',
                    'text' => $reply, 'image' => '', 'ts' => time() * 1000,
                ];
                $found = true;
                break;
            }
        }
        unset($t);
        if (!$found) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Ticket not found'], 404); }

        saveTickets($tickets);
        ob_end_clean();
        jsonResponse(['success' => true]);
    }

    if ($action === 'update_ticket_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ticketId = $input['ticket_id'] ?? '';
        $allowedStatuses = ['pending','replied','resolved','unresolved','closed'];
        $status = in_array($input['status'] ?? '', $allowedStatuses, true) ? $input['status'] : '';
        if ($status === '') { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Invalid status'], 400); }
        $tickets = loadTickets();
        $found = false;
        foreach ($tickets as &$t) {
            if ($t['id'] === $ticketId) {
                $t['status'] = $status;
                $t['updated_at'] = time();
                $labels = ['resolved' => 'ทีมงานตรวจสอบแล้ว: สำเร็จ', 'unresolved' => 'ทีมงานตรวจสอบแล้ว: ไม่สำเร็จ', 'closed' => 'ทีมงานปิด Ticket นี้แล้ว'];
                if (isset($labels[$status])) {
                    if (empty($t['messages'])) {
                        $t['messages'] = [['role' => 'user', 'sender_name' => $t['from_name'] ?? 'ผู้เล่น', 'text' => $t['message'] ?? '', 'image' => '', 'ts' => ((int)($t['created_at'] ?? time())) * 1000]];
                        if (!empty($t['admin_reply'])) $t['messages'][] = ['role' => 'admin', 'sender_name' => 'ทีมงาน', 'text' => $t['admin_reply'], 'image' => '', 'ts' => ((int)($t['updated_at'] ?? time())) * 1000];
                    }
                    $t['messages'][] = ['role' => 'system', 'sender_name' => 'ระบบ', 'text' => $labels[$status], 'image' => '', 'ts' => time() * 1000];
                }
                $found = true;
                break;
            }
        }
        unset($t);
        if (!$found) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Ticket not found'], 404); }
        saveTickets($tickets);
        ob_end_clean();
        jsonResponse(['success' => true]);
    }

    if ($action === 'send_ticket_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Not logged in'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ticketId = (string)($input['ticket_id'] ?? '');
        $text = trim(substr((string)($input['text'] ?? ''), 0, 2000));
        $image = (string)($input['image'] ?? '');
        if (strlen($image) > 2200000 || ($image !== '' && !preg_match('#^data:image/(jpeg|png|gif|webp);base64,[A-Za-z0-9+/=]+$#', $image))) {
            ob_end_clean(); jsonResponse(['success' => false, 'message' => 'รูปภาพไม่ถูกต้องหรือมีขนาดใหญ่เกินไป (สูงสุด 1.5 MB)'], 400);
        }
        if ($text === '' && $image === '') { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'กรุณาพิมพ์ข้อความหรือเลือกรูปภาพ'], 400); }
        $user = currentUser();
        $isAdminUser = isAdmin();
        $tickets = loadTickets();
        $found = false;
        foreach ($tickets as &$t) {
            if (($t['id'] ?? '') !== $ticketId) continue;
            if (!$isAdminUser && ($t['from_uid'] ?? '') !== ($user['uid'] ?? '')) {
                unset($t); ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
            }
            if (($t['status'] ?? '') === 'closed') {
                unset($t); ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Ticket is closed'], 409);
            }
            $t['messages'] = $t['messages'] ?? [];
            if (empty($t['messages'])) {
                $t['messages'][] = ['role' => 'user', 'sender_name' => $t['from_name'] ?? 'ผู้เล่น', 'text' => $t['message'] ?? '', 'image' => '', 'ts' => ((int)($t['created_at'] ?? time())) * 1000];
                if (!empty($t['admin_reply'])) $t['messages'][] = ['role' => 'admin', 'sender_name' => 'ทีมงาน', 'text' => $t['admin_reply'], 'image' => '', 'ts' => ((int)($t['updated_at'] ?? time())) * 1000];
            }
            $t['messages'][] = [
                'role' => $isAdminUser ? 'admin' : 'user',
                'sender_name' => $user['name'] ?? ($isAdminUser ? 'ทีมงาน' : 'ผู้เล่น'),
                'text' => $text,
                'image' => $image,
                'ts' => time() * 1000,
            ];
            if ($isAdminUser) $t['status'] = 'replied';
            elseif (in_array($t['status'] ?? '', ['resolved', 'unresolved'], true)) $t['status'] = 'pending';
            $t['updated_at'] = time();
            $t['last_msg'] = $text !== '' ? $text : '[รูปภาพ]';
            $found = true;
            break;
        }
        unset($t);
        if (!$found) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Ticket not found'], 404); }
        saveTickets($tickets);
        ob_end_clean(); jsonResponse(['success' => true]);
    }

    // ==================== FALLBACK ====================
    ob_end_clean();
    jsonResponse(['error' => 'Invalid action: ' . htmlspecialchars($action)], 400);

} catch (Throwable $e) {
    ob_end_clean();
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
