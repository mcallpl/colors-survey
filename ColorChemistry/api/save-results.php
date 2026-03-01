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

$firstName = trim($input['firstName'] ?? '');
$lastName = trim($input['lastName'] ?? '');
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$scores = $input['scores'] ?? null;

if (!$firstName || !$lastName || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Name and email required']);
    exit;
}

if (!$scores || !is_array($scores)) {
    http_response_code(400);
    echo json_encode(['error' => 'Scores required']);
    exit;
}

$red = intval($scores['red'] ?? 0);
$yellow = intval($scores['yellow'] ?? 0);
$green = intval($scores['green'] ?? 0);
$blue = intval($scores['blue'] ?? 0);

// Validate score ranges
foreach (['red' => $red, 'yellow' => $yellow, 'green' => $green, 'blue' => $blue] as $color => $score) {
    if ($score < 12 || $score > 48) {
        http_response_code(400);
        echo json_encode(['error' => "Invalid $color score: $score"]);
        exit;
    }
}

if ($red + $yellow + $green + $blue !== 120) {
    http_response_code(400);
    echo json_encode(['error' => 'Scores must total 120']);
    exit;
}

// Determine dominant color
$colorScores = ['red' => $red, 'yellow' => $yellow, 'green' => $green, 'blue' => $blue];
arsort($colorScores);
$dominant = array_key_first($colorScores);

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        INSERT INTO survey_results (first_name, last_name, email, red_score, yellow_score, green_score, blue_score, dominant_color, completed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$firstName, $lastName, $email, $red, $yellow, $green, $blue, $dominant]);
    $insertId = $pdo->lastInsertId();

    // Send notification email
    sendNotificationEmail($firstName, $lastName, $email, $red, $yellow, $green, $blue, $dominant);

    echo json_encode(['success' => true, 'id' => $insertId]);
} catch (PDOException $e) {
    error_log('ColorChemistry save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save results']);
}

function sendNotificationEmail($firstName, $lastName, $email, $red, $yellow, $green, $blue, $dominant) {
    $colorHex = ['red' => '#EF4444', 'yellow' => '#F59E0B', 'green' => '#10B981', 'blue' => '#3B82F6'];
    $colorLight = ['red' => '#FEE2E2', 'yellow' => '#FEF3C7', 'green' => '#D1FAE5', 'blue' => '#DBEAFE'];
    $colorNames = [
        'red' => 'The Ambitious Trailblazer',
        'yellow' => 'The Energetic Motivator',
        'green' => 'The Reliable Harmonizer',
        'blue' => 'The Careful Examiner'
    ];

    $total = $red + $yellow + $green + $blue;
    $redPct = round($red / $total * 100);
    $yellowPct = round($yellow / $total * 100);
    $greenPct = round($green / $total * 100);
    $bluePct = round($blue / $total * 100);

    $domHex = $colorHex[$dominant];
    $domLight = $colorLight[$dominant];
    $domName = $colorNames[$dominant];
    $domUpper = ucfirst($dominant);
    $fullName = htmlspecialchars("$firstName $lastName");
    $safeEmail = htmlspecialchars($email);
    $date = date('F j, Y \a\t g:i A T');

    $barWidth = function($score) { return round($score / 48 * 100); };

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<div style="max-width:580px;margin:0 auto;padding:32px 16px;">

    <!-- Header -->
    <div style="background:#0f172a;border-radius:20px 20px 0 0;padding:32px;text-align:center;">
        <div style="margin-bottom:16px;">
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#EF4444;margin:0 3px;"></span>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#F59E0B;margin:0 3px;"></span>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10B981;margin:0 3px;"></span>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#3B82F6;margin:0 3px;"></span>
        </div>
        <h1 style="color:#fff;font-size:24px;font-weight:800;margin:0 0 4px 0;">Color Chemistry</h1>
        <p style="color:#94a3b8;font-size:13px;margin:0;">New Assessment Completed</p>
    </div>

    <!-- Gradient bar -->
    <div style="height:4px;background:linear-gradient(90deg,#EF4444,#F59E0B,#10B981,#3B82F6);"></div>

    <!-- Body -->
    <div style="background:#ffffff;padding:32px;border-radius:0 0 20px 20px;">

        <!-- Person info -->
        <div style="text-align:center;margin-bottom:28px;">
            <div style="display:inline-block;width:56px;height:56px;border-radius:50%;background:$domHex;line-height:56px;text-align:center;color:#fff;font-size:20px;font-weight:700;margin-bottom:12px;">
                {$firstName[0]}{$lastName[0]}
            </div>
            <h2 style="color:#0f172a;font-size:22px;font-weight:700;margin:0 0 4px 0;">$fullName</h2>
            <p style="color:#64748b;font-size:13px;margin:0 0 4px 0;">$safeEmail</p>
            <p style="color:#94a3b8;font-size:12px;margin:0;">$date</p>
        </div>

        <!-- Dominant badge -->
        <div style="text-align:center;margin-bottom:28px;">
            <div style="display:inline-block;background:$domLight;border:2px solid $domHex;border-radius:30px;padding:10px 24px;">
                <span style="color:$domHex;font-size:16px;font-weight:800;text-transform:uppercase;letter-spacing:1px;">$domUpper</span>
                <span style="color:#64748b;font-size:13px;margin-left:6px;">$domName</span>
            </div>
        </div>

        <!-- Score bars -->
        <div style="margin-bottom:8px;">
            <!-- Red -->
            <div style="display:flex;align-items:center;margin-bottom:14px;">
                <div style="width:12px;height:12px;border-radius:50%;background:#EF4444;margin-right:10px;"></div>
                <div style="width:55px;color:#334155;font-size:13px;font-weight:600;">Red</div>
                <div style="flex:1;height:20px;background:#f1f5f9;border-radius:10px;overflow:hidden;margin-right:10px;">
                    <div style="height:100%;width:{$barWidth($red)}%;background:linear-gradient(90deg,#EF4444,#F87171);border-radius:10px;"></div>
                </div>
                <div style="width:32px;text-align:right;color:#EF4444;font-size:16px;font-weight:800;">$red</div>
            </div>
            <!-- Yellow -->
            <div style="display:flex;align-items:center;margin-bottom:14px;">
                <div style="width:12px;height:12px;border-radius:50%;background:#F59E0B;margin-right:10px;"></div>
                <div style="width:55px;color:#334155;font-size:13px;font-weight:600;">Yellow</div>
                <div style="flex:1;height:20px;background:#f1f5f9;border-radius:10px;overflow:hidden;margin-right:10px;">
                    <div style="height:100%;width:{$barWidth($yellow)}%;background:linear-gradient(90deg,#F59E0B,#FBBF24);border-radius:10px;"></div>
                </div>
                <div style="width:32px;text-align:right;color:#F59E0B;font-size:16px;font-weight:800;">$yellow</div>
            </div>
            <!-- Green -->
            <div style="display:flex;align-items:center;margin-bottom:14px;">
                <div style="width:12px;height:12px;border-radius:50%;background:#10B981;margin-right:10px;"></div>
                <div style="width:55px;color:#334155;font-size:13px;font-weight:600;">Green</div>
                <div style="flex:1;height:20px;background:#f1f5f9;border-radius:10px;overflow:hidden;margin-right:10px;">
                    <div style="height:100%;width:{$barWidth($green)}%;background:linear-gradient(90deg,#10B981,#34D399);border-radius:10px;"></div>
                </div>
                <div style="width:32px;text-align:right;color:#10B981;font-size:16px;font-weight:800;">$green</div>
            </div>
            <!-- Blue -->
            <div style="display:flex;align-items:center;margin-bottom:14px;">
                <div style="width:12px;height:12px;border-radius:50%;background:#3B82F6;margin-right:10px;"></div>
                <div style="width:55px;color:#334155;font-size:13px;font-weight:600;">Blue</div>
                <div style="flex:1;height:20px;background:#f1f5f9;border-radius:10px;overflow:hidden;margin-right:10px;">
                    <div style="height:100%;width:{$barWidth($blue)}%;background:linear-gradient(90deg,#3B82F6,#60A5FA);border-radius:10px;"></div>
                </div>
                <div style="width:32px;text-align:right;color:#3B82F6;font-size:16px;font-weight:800;">$blue</div>
            </div>
        </div>

        <!-- Percentage breakdown -->
        <div style="background:#f8fafc;border-radius:12px;padding:16px;text-align:center;">
            <div style="display:inline-block;margin:0 12px;">
                <span style="color:#EF4444;font-size:18px;font-weight:800;">{$redPct}%</span>
                <span style="color:#94a3b8;font-size:11px;display:block;">Red</span>
            </div>
            <div style="display:inline-block;margin:0 12px;">
                <span style="color:#F59E0B;font-size:18px;font-weight:800;">{$yellowPct}%</span>
                <span style="color:#94a3b8;font-size:11px;display:block;">Yellow</span>
            </div>
            <div style="display:inline-block;margin:0 12px;">
                <span style="color:#10B981;font-size:18px;font-weight:800;">{$greenPct}%</span>
                <span style="color:#94a3b8;font-size:11px;display:block;">Green</span>
            </div>
            <div style="display:inline-block;margin:0 12px;">
                <span style="color:#3B82F6;font-size:18px;font-weight:800;">{$bluePct}%</span>
                <span style="color:#94a3b8;font-size:11px;display:block;">Blue</span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <p style="text-align:center;color:#94a3b8;font-size:11px;margin-top:20px;">
        Color Chemistry &mdash; Personality Color Assessment<br>
        <a href="https://peoplestar.com/ColorChemistry/admin.html" style="color:#6366f1;">View Admin Dashboard</a>
    </p>
</div>
</body>
</html>
HTML;

    $subject = "Color Chemistry: $fullName finished the assessment ($domUpper)";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Color Chemistry <noreply@peoplestar.com>',
        'Reply-To: noreply@peoplestar.com',
        'Bcc: mcallpl@gmail.com'
    ];

    mail($email, $subject, $body, implode("\r\n", $headers));
}
