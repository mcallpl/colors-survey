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

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$firstName = trim($input['firstName'] ?? '');
$lastName = trim($input['lastName'] ?? '');

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email address required']);
    exit;
}

if (strlen($firstName) < 1 || strlen($lastName) < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'First and last name required']);
    exit;
}

// Rate limiting: max 5 codes per email per hour
$codesDir = __DIR__ . '/codes';
if (!is_dir($codesDir)) {
    mkdir($codesDir, 0755, true);
}

$emailHash = hash('sha256', strtolower($email));
$codeFile = $codesDir . '/' . $emailHash . '.json';

if (file_exists($codeFile)) {
    $existing = json_decode(file_get_contents($codeFile), true);
    if (isset($existing['attempts']) && $existing['attempts'] >= 5 &&
        isset($existing['firstAttempt']) && (time() - $existing['firstAttempt']) < 3600) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many attempts. Please try again later.']);
        exit;
    }
    // Reset counter if over an hour
    if (isset($existing['firstAttempt']) && (time() - $existing['firstAttempt']) >= 3600) {
        $existing['attempts'] = 0;
    }
}

// Generate 6-digit code
$code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

// Store code
$data = [
    'code' => $code,
    'email' => $email,
    'firstName' => $firstName,
    'lastName' => $lastName,
    'expires' => time() + 600, // 10 minutes
    'attempts' => ($existing['attempts'] ?? 0) + 1,
    'firstAttempt' => $existing['firstAttempt'] ?? time(),
    'verifyAttempts' => 0
];
file_put_contents($codeFile, json_encode($data));

// Send email
$subject = 'Your Color Chemistry Confirmation Code';
$htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #0a0a0a; padding: 40px 20px;">
    <div style="max-width: 480px; margin: 0 auto; background: #1a1a2e; border-radius: 16px; overflow: hidden; border: 1px solid #333;">
        <div style="background: linear-gradient(135deg, #ef4444, #f59e0b, #10b981, #3b82f6); padding: 4px;">
            <div style="background: #1a1a2e; padding: 30px; text-align: center;">
                <h1 style="color: #fff; font-size: 24px; margin: 0 0 8px 0;">Color Chemistry</h1>
                <p style="color: #888; font-size: 14px; margin: 0;">Email Confirmation</p>
            </div>
        </div>
        <div style="padding: 30px; text-align: center;">
            <p style="color: #ccc; font-size: 16px; margin: 0 0 8px 0;">Hi ' . htmlspecialchars($firstName) . ',</p>
            <p style="color: #999; font-size: 14px; margin: 0 0 24px 0;">Enter this code to confirm your email:</p>
            <div style="background: #0f0f23; border-radius: 12px; padding: 20px; margin: 0 0 24px 0;">
                <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #fff;">' . $code . '</span>
            </div>
            <p style="color: #666; font-size: 12px; margin: 0;">This code expires in 10 minutes.</p>
        </div>
    </div>
</body>
</html>';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Color Chemistry <noreply@peoplestar.com>',
    'Reply-To: noreply@peoplestar.com',
    'X-Mailer: PHP/' . phpversion()
];

$sent = mail($email, $subject, $htmlBody, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Verification code sent']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email. Please try again.']);
}
