<?php
date_default_timezone_set('America/Los_Angeles');

define('DB_HOST', 'localhost');
define('DB_NAME', 'pwsdb');
define('DB_USER', 'mcallpl');
define('DB_PASS', 'amazing123');

define('ADMIN_PASSWORD_HASH', '$2y$12$ufPGokeCVe4FDmmlBG9gNu5AnlTP67oVy//sXxiDGaa7a/RiM0LOG');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

function validateAdminToken($token) {
    if (!$token || strlen($token) < 32) return false;
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT token FROM admin_sessions WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    return $stmt->fetch() !== false;
}

function getAdminToken() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        return $m[1];
    }
    return $_GET['token'] ?? $_POST['token'] ?? '';
}
