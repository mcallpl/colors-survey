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
require_once __DIR__ . '/stripe-config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email required']);
    exit;
}

$email = strtolower($email);

// Check if already paid
$pdo = getDB();
$stmt = $pdo->prepare('SELECT id FROM payments WHERE email = ? AND status IN ("completed","comped")');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['already_paid' => true]);
    exit;
}

// Create Stripe Checkout Session
$result = stripeRequest('/checkout/sessions', [
    'mode' => 'payment',
    'line_items[0][price]' => STRIPE_PRICE_ID,
    'line_items[0][quantity]' => 1,
    'customer_email' => $email,
    'success_url' => 'https://peoplestar.com/ColorChemistry/?payment=success&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'https://peoplestar.com/ColorChemistry/?payment=cancelled',
    'metadata[email]' => $email,
]);

if ($result['code'] !== 200 || empty($result['body']['url'])) {
    error_log('ColorChemistry Stripe error: ' . json_encode($result['body']));
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create checkout session']);
    exit;
}

// Store pending payment
$sessionId = $result['body']['id'];
$stmt = $pdo->prepare('INSERT INTO payments (email, stripe_session_id, status) VALUES (?, ?, "pending")');
$stmt->execute([$email, $sessionId]);

echo json_encode(['checkout_url' => $result['body']['url']]);
