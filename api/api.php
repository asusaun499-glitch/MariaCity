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
