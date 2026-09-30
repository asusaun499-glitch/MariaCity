<?php
require_once 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$dataDir = __DIR__ . '/data';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// 1. Get Map Progress
if ($action === 'get_map') {
    $mapFile = $dataDir . '/map.json';
    if (file_exists($mapFile)) {
        echo file_get_contents($mapFile);
    } else {
        echo json_encode(['progress' => 0]);
    }
    exit;
}

// 2. Update Map Progress (Admin only)
if ($action === 'update_map' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isDeveloper()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $progress = (int)($input['progress'] ?? 0);
    
    file_put_contents($dataDir . '/map.json', json_encode(['progress' => $progress]));
    echo json_encode(['success' => true, 'progress' => $progress]);
    exit;
}

// 3. Add Chat Log
if ($action === 'add_log' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['user']['uid']);
    $logFile = $dataDir . "/logs_{$uid}.json";
    
    $logs = [];
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
    }
    
    $logs[] = [
        'sender' => $input['sender'],
        'text' => $input['text'],
        'timestamp' => time() * 1000
    ];
    
    // กรอง log ให้เก็บแค่ 14 วันย้อนหลัง
    $fourteenDaysMs = 14 * 24 * 60 * 60 * 1000;
    $now = time() * 1000;
    $validLogs = array_filter($logs, function($log) use ($now, $fourteenDaysMs) {
        return ($now - $log['timestamp']) <= $fourteenDaysMs;
    });
    
    file_put_contents($logFile, json_encode(array_values($validLogs)));
    echo json_encode(['success' => true]);
    exit;
}

// 4. Get Chat Logs
if ($action === 'get_logs') {
    if (!isset($_SESSION['user'])) {
        echo json_encode([]);
        exit;
    }
    
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['user']['uid']);
    $logFile = $dataDir . "/logs_{$uid}.json";
    
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        echo json_encode($logs);
    } else {
        echo json_encode([]);
    }
    exit;
}

// 5. Clear Chat Logs
if ($action === 'clear_logs') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false]);
        exit;
    }
    $uid = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['user']['uid']);
    $logFile = $dataDir . "/logs_{$uid}.json";
    
    if (file_exists($logFile)) {
        unlink($logFile);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
