<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-config.php';

// Require admin auth
$token = getAdminToken();
if (!validateAdminToken($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();

// Ensure settings table exists
$pdo->exec('CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = "vip_code"');
    $stmt->execute();
    $row = $stmt->fetch();
    $code = $row ? $row['setting_value'] : 'amazing123';
    echo json_encode(['code' => $code]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $newCode = trim($input['code'] ?? '');

    if (strlen($newCode) < 4) {
        http_response_code(400);
        echo json_encode(['error' => 'Code must be at least 4 characters']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES ("vip_code", ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()');
    $stmt->execute([$newCode, $newCode]);

    echo json_encode(['success' => true, 'code' => $newCode]);
}
