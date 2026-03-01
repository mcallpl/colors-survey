<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/stripe-config.php';

$email = filter_var(trim($_GET['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$sessionId = trim($_GET['session_id'] ?? '');

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email required']);
    exit;
}

$email = strtolower($email);
$pdo = getDB();

// If session_id provided, verify with Stripe and update DB
if ($sessionId && preg_match('/^cs_/', $sessionId)) {
    $result = stripeGet('/checkout/sessions/' . urlencode($sessionId));
    if ($result['code'] === 200 && ($result['body']['payment_status'] ?? '') === 'paid') {
        $metaEmail = strtolower($result['body']['metadata']['email'] ?? '');
        if ($metaEmail === $email) {
            $paymentIntent = $result['body']['payment_intent'] ?? null;
            $stmt = $pdo->prepare('UPDATE payments SET status = "completed", completed_at = NOW() WHERE stripe_session_id = ? AND status = "pending"');
            $stmt->execute([$sessionId]);

            // If no row was updated (webhook already handled it, or row missing), insert
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT id FROM payments WHERE stripe_session_id = ?');
                $check->execute([$sessionId]);
                if (!$check->fetch()) {
                    $ins = $pdo->prepare('INSERT INTO payments (email, stripe_session_id, status, completed_at) VALUES (?, ?, "completed", NOW())');
                    $ins->execute([$email, $sessionId]);
                }
            }
        }
    }
}

// Check if paid
$stmt = $pdo->prepare('SELECT id FROM payments WHERE email = ? AND status IN ("completed","comped")');
$stmt->execute([$email]);
$paid = $stmt->fetch() !== false;

echo json_encode(['paid' => $paid]);
