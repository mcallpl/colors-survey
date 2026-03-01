<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$code = trim($input['code'] ?? '');

if (!$email || !$code) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and code required']);
    exit;
}

$codesDir = __DIR__ . '/codes';
$emailHash = hash('sha256', strtolower($email));
$codeFile = $codesDir . '/' . $emailHash . '.json';

if (!file_exists($codeFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'No verification code found. Please request a new one.']);
    exit;
}

$data = json_decode(file_get_contents($codeFile), true);

// Check max verify attempts (prevent brute force)
if (($data['verifyAttempts'] ?? 0) >= 10) {
    unlink($codeFile);
    http_response_code(429);
    echo json_encode(['error' => 'Too many incorrect attempts. Please request a new code.']);
    exit;
}

// Check expiration
if (time() > ($data['expires'] ?? 0)) {
    unlink($codeFile);
    http_response_code(400);
    echo json_encode(['error' => 'Code expired. Please request a new one.']);
    exit;
}

// Verify code
if ($code !== $data['code']) {
    $data['verifyAttempts'] = ($data['verifyAttempts'] ?? 0) + 1;
    file_put_contents($codeFile, json_encode($data));
    http_response_code(400);
    $remaining = 10 - $data['verifyAttempts'];
    echo json_encode(['error' => "Incorrect code. $remaining attempts remaining."]);
    exit;
}

// Success - clean up code file
unlink($codeFile);

// Check for existing survey results in database
$existingResults = null;
try {
    require_once __DIR__ . '/db-config.php';
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT red_score, yellow_score, green_score, blue_score FROM survey_results WHERE email = ? ORDER BY completed_at DESC LIMIT 1');
    $stmt->execute([strtolower($data['email'])]);
    $row = $stmt->fetch();
    if ($row) {
        $existingResults = [
            'red' => (int)$row['red_score'],
            'yellow' => (int)$row['yellow_score'],
            'green' => (int)$row['green_score'],
            'blue' => (int)$row['blue_score']
        ];
    }
} catch (Exception $e) {
    // Non-critical - just skip results lookup
}

$response = [
    'success' => true,
    'user' => [
        'firstName' => $data['firstName'],
        'lastName' => $data['lastName'],
        'email' => $data['email'],
        'verifiedAt' => date('c')
    ]
];
if ($existingResults) {
    $response['existingResults'] = $existingResults;
}
echo json_encode($response);
