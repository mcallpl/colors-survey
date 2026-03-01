<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db-config.php';

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (!$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Password required']);
    exit;
}

// Rate limiting by IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = __DIR__ . '/codes/login_' . md5($ip) . '.json';
if (file_exists($rateLimitFile)) {
    $rl = json_decode(file_get_contents($rateLimitFile), true);
    if (($rl['attempts'] ?? 0) >= 10 && (time() - ($rl['first'] ?? 0)) < 3600) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many login attempts. Try again later.']);
        exit;
    }
    if ((time() - ($rl['first'] ?? 0)) >= 3600) {
        $rl = ['attempts' => 0, 'first' => time()];
    }
} else {
    $rl = ['attempts' => 0, 'first' => time()];
}

if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
    $rl['attempts']++;
    file_put_contents($rateLimitFile, json_encode($rl));
    http_response_code(401);
    echo json_encode(['error' => 'Invalid password']);
    exit;
}

// Clear rate limit on success
if (file_exists($rateLimitFile)) unlink($rateLimitFile);

// Generate session token
$token = bin2hex(random_bytes(32));
$pdo = getDB();

// Clean expired sessions
$pdo->exec("DELETE FROM admin_sessions WHERE expires_at < NOW()");

// Create new session (24 hours)
$stmt = $pdo->prepare('INSERT INTO admin_sessions (token, expires_at) VALUES (?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
$stmt->execute([$token]);

echo json_encode(['success' => true, 'token' => $token]);
