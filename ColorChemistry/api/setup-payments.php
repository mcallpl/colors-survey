<?php
require_once __DIR__ . '/db-config.php';

try {
    $pdo = getDB();
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            stripe_session_id VARCHAR(255) DEFAULT NULL,
            amount_cents INT UNSIGNED NOT NULL DEFAULT 499,
            status ENUM("pending","completed","comped","refunded") NOT NULL DEFAULT "pending",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            INDEX idx_email (email),
            UNIQUE INDEX idx_stripe_session (stripe_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    echo "payments table created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
