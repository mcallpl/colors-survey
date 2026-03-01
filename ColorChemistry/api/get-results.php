<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db-config.php';

$token = getAdminToken();
if (!validateAdminToken($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();

$dateFrom = $_GET['date_from'] ?? $_GET['date'] ?? '';
$dateTo = $_GET['date_to'] ?? $_GET['date'] ?? '';

// Validate date format
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($datePattern, $dateFrom) || !preg_match($datePattern, $dateTo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid date required (YYYY-MM-DD)']);
    exit;
}

if (strtotime($dateFrom) === false || strtotime($dateTo) === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date']);
    exit;
}

// Fetch results
$stmt = $pdo->prepare('
    SELECT id, first_name, last_name, email, red_score, yellow_score, green_score, blue_score, dominant_color, completed_at
    FROM survey_results
    WHERE DATE(completed_at) BETWEEN ? AND ?
    ORDER BY completed_at ASC
');
$stmt->execute([$dateFrom, $dateTo]);
$results = $stmt->fetchAll();

// Compute summary
$total = count($results);
$summary = [
    'total_participants' => $total,
    'avg_red' => 0, 'avg_yellow' => 0, 'avg_green' => 0, 'avg_blue' => 0,
    'color_distribution' => ['red' => 0, 'yellow' => 0, 'green' => 0, 'blue' => 0]
];

if ($total > 0) {
    $sumRed = $sumYellow = $sumGreen = $sumBlue = 0;
    foreach ($results as $r) {
        $sumRed += $r['red_score'];
        $sumYellow += $r['yellow_score'];
        $sumGreen += $r['green_score'];
        $sumBlue += $r['blue_score'];
        $summary['color_distribution'][$r['dominant_color']]++;
    }
    $summary['avg_red'] = round($sumRed / $total, 1);
    $summary['avg_yellow'] = round($sumYellow / $total, 1);
    $summary['avg_green'] = round($sumGreen / $total, 1);
    $summary['avg_blue'] = round($sumBlue / $total, 1);
}

echo json_encode([
    'success' => true,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'results' => $results,
    'summary' => $summary
]);
