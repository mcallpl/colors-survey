<?php
// One-time database setup. DELETE THIS FILE after running successfully.
if (($_GET['key'] ?? '') !== 'setupDT2026') { http_response_code(403); die('Unauthorized'); }
header('Content-Type: text/plain');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=pwsdb;charset=utf8mb4', 'mcallpl', 'amazing123',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS survey_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            red_score TINYINT UNSIGNED NOT NULL,
            yellow_score TINYINT UNSIGNED NOT NULL,
            green_score TINYINT UNSIGNED NOT NULL,
            blue_score TINYINT UNSIGNED NOT NULL,
            dominant_color ENUM('red','yellow','green','blue') NOT NULL,
            completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_completed_at (completed_at),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table 'survey_results' created.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_sessions (
            token VARCHAR(64) PRIMARY KEY,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table 'admin_sessions' created.\n";

    // Verify
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "\nAll tables in pwsdb: " . implode(', ', $tables) . "\n";
    echo "\nSetup complete! DELETE THIS FILE NOW.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
