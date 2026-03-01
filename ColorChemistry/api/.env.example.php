<?php
// ============================================================
// SECRETS TEMPLATE — Copy this file to .env.php and fill in
// your real values. The .env.php file is gitignored.
// ============================================================

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Admin password hash (bcrypt) — generate with: php -r "echo password_hash('yourpass', PASSWORD_BCRYPT);"
define('ADMIN_PASSWORD_HASH', '$2y$12$...');

// Stripe
define('STRIPE_SECRET_KEY',      'sk_live_...');
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_...');
define('STRIPE_PRICE_ID',        'price_...');
define('STRIPE_WEBHOOK_SECRET',  'whsec_...');

// VIP access code
define('VIP_ACCESS_CODE', 'your_vip_code');
