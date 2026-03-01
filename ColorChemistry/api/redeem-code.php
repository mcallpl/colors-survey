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

require_once __DIR__ . '/db-config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$code = trim($input['code'] ?? '');

if (!$email || !$code) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and access code required']);
    exit;
}

$email = strtolower($email);
$pdo = getDB();

// Get VIP code from database, fall back to config
$vipCode = 'amazing123';
try {
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = "vip_code"');
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row) $vipCode = $row['setting_value'];
} catch (Exception $e) {}

// Validate code (case-sensitive)
if ($code !== $vipCode) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid access code']);
    exit;
}

// Check if already paid/comped
$stmt = $pdo->prepare('SELECT id FROM payments WHERE email = ? AND status IN ("completed","comped")');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'message' => 'Already unlocked']);
    exit;
}

// Grant access
$vipSessionId = 'vip_' . bin2hex(random_bytes(16));
$stmt = $pdo->prepare('INSERT INTO payments (email, stripe_session_id, amount_cents, status, completed_at) VALUES (?, ?, 0, "comped", NOW())');
$stmt->execute([$email, $vipSessionId]);

echo json_encode(['success' => true]);
