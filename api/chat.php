<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'รองรับเฉพาะ POST']);
}

$apiKey = getenv('GEMINI_API_KEY2') ?: (getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY2'] ?? ($_ENV['GEMINI_API_KEY'] ?? '')));
if (!$apiKey) {
    respond(500, ['error' => 'ยังไม่ได้ตั้งค่า GEMINI_API_KEY หรือ GEMINI_API_KEY2 บนเซิร์ฟเวอร์']);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true); 

if (!is_array($body)) {
    respond(400, ['error' => 'รูปแบบข้อมูลไม่ถูกต้อง']);
}

$message = trim((string)($body['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 6000) {
    respond(400, ['error' => 'กรุณาส่งข้อความยาวไม่เกิน 6,000 ตัวอักษร']);
}

$history = $body['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

// จำกัดประวัติไว้ 8 ข้อความ เพื่อลดเวลาและค่าใช้จ่าย
$contents = [];
foreach (array_slice($history, -8) as $item) {
    if (!is_array($item)) continue;

    $role = ($item['role'] ?? '') === 'model' ? 'model' : 'user';
    $text = trim((string)($item['text'] ?? ''));

    if ($text !== '') {
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => mb_substr($text, 0, 3000)]],
        ];
    }
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

// ใส่ข้อมูลเฉพาะโปรเจกต์ที่ต้องการให้ AI ใช้ตอบใน GEMINI_PROJECT_CONTEXT
$projectContext = getenv('GEMINI_PROJECT_CONTEXT') ?: '';

$payload = [
    'system_instruction' => [
        'parts' => [[
            'text' => "คุณคือผู้ช่วย AI ของโปรเจกต์นี้ ตอบภาษาเดียวกับผู้ใช้ ใช้คำตอบสั้น ชัดเจน ตรงคำถาม ไม่คิดหรืออธิบายยืดยาว หากไม่มีข้อมูลให้บอกตามตรง ห้ามแต่งข้อมูลขึ้นเอง"
                . ($projectContext !== '' ? "\n\nข้อมูลอ้างอิงของโปรเจกต์:\n" . $projectContext : ''),
        ]],
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.4,
        'maxOutputTokens' => 400,
    ],
];

$model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
    . rawurlencode($model) . ':generateContent';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);

$result = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($result === false) {
    error_log('Gemini request failed: ' . $curlError);
    respond(502, ['error' => 'เชื่อมต่อ AI ไม่สำเร็จ กรุณาลองใหม่']);
}

$decoded = json_decode($result, true);
if ($status < 200 || $status >= 300) {
    error_log('Gemini API returned HTTP ' . $status);
    respond(502, ['error' => 'AI ตอบกลับผิดพลาด กรุณาลองใหม่']);
}

$answer = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
if (!is_string($answer) || trim($answer) === '') {
    respond(502, ['error' => 'AI ไม่มีคำตอบ กรุณาลองใหม่']);
}

respond(200, ['reply' => trim($answer)]);
