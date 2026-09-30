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
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['success' => false, 'message' => 'Not logged in'], 401); }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $user = currentUser();
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
        if (!isLoggedIn()) { ob_end_clean(); jsonResponse(['messages' => []], 401); }
        $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', currentUser()['uid'] ?? '');
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
