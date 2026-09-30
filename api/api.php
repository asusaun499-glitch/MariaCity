<?php
require_once 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// ==================== MAP PROGRESS ====================
if ($action === 'get_map') {
    $mapFile = DATA_DIR . '/map.json';
    echo file_exists($mapFile) ? file_get_contents($mapFile) : json_encode(['progress' => 0]);
    exit;
}

if ($action === 'update_map' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    $progress = max(0, min(100, (int)($input['progress'] ?? 0)));
    file_put_contents(DATA_DIR . '/map.json', json_encode(['progress' => $progress]));
    jsonResponse(['success' => true, 'progress' => $progress]);
}

// ==================== CHAT LOGS ====================
if ($action === 'add_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Not logged in'], 401);
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', currentUser()['uid']);
    $logFile = DATA_DIR . "/logs_{$uid}.json";
    $logs = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?: []) : [];
    $logs[] = [
        'sender'    => $input['sender'] ?? 'user',
        'text'      => substr($input['text'] ?? '', 0, 2000),
        'type'      => $input['type'] ?? 'text',
        'timestamp' => time() * 1000
    ];
    // 14-Day TTL
    $ttl = 14 * 86400 * 1000;
    $now = time() * 1000;
    $logs = array_values(array_filter($logs, fn($l) => ($now - $l['timestamp']) <= $ttl));
    file_put_contents($logFile, json_encode($logs, JSON_UNESCAPED_UNICODE));
    jsonResponse(['success' => true]);
}

if ($action === 'get_logs') {
    if (!isLoggedIn()) jsonResponse([], 401);
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', currentUser()['uid']);
    $logFile = DATA_DIR . "/logs_{$uid}.json";
    $logs = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?: []) : [];
    echo json_encode($logs);
    exit;
}

if ($action === 'clear_logs') {
    if (!isLoggedIn()) jsonResponse(['success' => false], 401);
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', currentUser()['uid']);
    $logFile = DATA_DIR . "/logs_{$uid}.json";
    if (file_exists($logFile)) unlink($logFile);
    jsonResponse(['success' => true]);
}

// Admin: Get ALL logs from all users
if ($action === 'all_logs') {
    if (!isAdmin()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    $allLogs = [];
    foreach (glob(DATA_DIR . '/logs_*.json') as $file) {
        $fileLogs = json_decode(file_get_contents($file), true) ?: [];
        $uid = basename($file, '.json');
        foreach ($fileLogs as $log) {
            $log['uid'] = $uid;
            $allLogs[] = $log;
        }
    }
    usort($allLogs, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
    jsonResponse(['logs' => array_slice($allLogs, 0, 500)]);
}

// ==================== TICKETS ====================
function loadTickets(): array {
    $file = DATA_DIR . '/tickets.json';
    return file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}
function saveTickets(array $tickets): void {
    file_put_contents(DATA_DIR . '/tickets.json', json_encode($tickets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Create Ticket (User)
if ($action === 'create_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Not logged in'], 401);
    $input = json_decode(file_get_contents('php://input'), true);
    $user = currentUser();
    $tickets = loadTickets();
    $newTicket = [
        'id'          => 'TKT-' . str_pad(count($tickets) + 1, 3, '0', STR_PAD_LEFT),
        'type'        => $input['type'] ?? 'general',
        'status'      => 'pending',
        'from_uid'    => $user['uid'],
        'from_name'   => $user['name'],
        'from_email'  => $user['email'],
        'message'     => substr($input['message'] ?? '', 0, 1000),
        'evidence'    => [],
        'admin_reply' => null,
        'created_at'  => time(),
        'updated_at'  => time(),
    ];
    array_unshift($tickets, $newTicket);
    saveTickets($tickets);
    jsonResponse(['success' => true, 'ticket_id' => $newTicket['id']]);
}

// My Tickets (User)
if ($action === 'my_tickets') {
    if (!isLoggedIn()) jsonResponse(['tickets' => []]);
    $user = currentUser();
    $tickets = loadTickets();
    $myTickets = array_values(array_filter($tickets, fn($t) => $t['from_uid'] === $user['uid']));
    jsonResponse(['tickets' => $myTickets]);
}

// All Tickets (Admin)
if ($action === 'all_tickets') {
    if (!isAdmin()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    jsonResponse(['tickets' => loadTickets()]);
}

// Admin Reply to Ticket
if ($action === 'reply_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = $input['ticket_id'] ?? '';
    $reply = substr($input['reply'] ?? '', 0, 2000);
    $tickets = loadTickets();
    $found = false;
    foreach ($tickets as &$t) {
        if ($t['id'] === $ticketId) {
            $t['admin_reply'] = $reply;
            $t['status'] = 'replied';
            $t['updated_at'] = time();
            $found = true;
            break;
        }
    }
    if (!$found) jsonResponse(['success' => false, 'message' => 'Ticket not found'], 404);
    saveTickets($tickets);
    jsonResponse(['success' => true]);
}

// Update Ticket Status (Admin)
if ($action === 'update_ticket_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = $input['ticket_id'] ?? '';
    $status = in_array($input['status'] ?? '', ['pending','replied','closed']) ? $input['status'] : 'pending';
    $tickets = loadTickets();
    foreach ($tickets as &$t) {
        if ($t['id'] === $ticketId) { $t['status'] = $status; $t['updated_at'] = time(); break; }
    }
    saveTickets($tickets);
    jsonResponse(['success' => true]);
}

// ==================== FALLBACK ====================
jsonResponse(['error' => 'Invalid action: ' . $action], 400);
