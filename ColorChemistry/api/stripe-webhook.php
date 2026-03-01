<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/stripe-config.php';

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
if (!verifyStripeWebhook($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);

if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $sessionId = $session['id'] ?? '';
    $email = strtolower($session['metadata']['email'] ?? '');
    $paymentStatus = $session['payment_status'] ?? '';

    if ($sessionId && $email && $paymentStatus === 'paid') {
        $pdo = getDB();

        // Try to update existing pending row
        $stmt = $pdo->prepare('UPDATE payments SET status = "completed", completed_at = NOW() WHERE stripe_session_id = ? AND status = "pending"');
        $stmt->execute([$sessionId]);

        // If no row existed (edge case), insert one
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

http_response_code(200);
echo json_encode(['received' => true]);

function verifyStripeWebhook($payload, $sigHeader, $secret) {
    if (!$sigHeader) return false;

    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $sigHeader) as $element) {
        $parts = explode('=', $element, 2);
        if (count($parts) !== 2) continue;
        if ($parts[0] === 't') $timestamp = $parts[1];
        if ($parts[0] === 'v1') $signatures[] = $parts[1];
    }

    if (!$timestamp || empty($signatures)) return false;

    // Reject if older than 5 minutes
    if (abs(time() - intval($timestamp)) > 300) return false;

    $signedPayload = $timestamp . '.' . $payload;
    $expectedSig = hash_hmac('sha256', $signedPayload, $secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expectedSig, $sig)) return true;
    }

    return false;
}
