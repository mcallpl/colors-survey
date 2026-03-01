<?php
// Stripe API Configuration
define('STRIPE_SECRET_KEY',      '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');
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_51RfnHU2KfXjg5R6Rb5zfLwWUybTsk7bhJQvOy0YWFg7gpONCFVxR3oGcGyy4QY7Kx8LoAm4xDwVvfNY13t3IjNqT00XxbkpWsa');
define('STRIPE_PRICE_ID',        'price_1T5uhm2KfXjg5R6RSwav9keh');
define('STRIPE_WEBHOOK_SECRET',  'whsec_kCuJTBZCoy2K29aFyY04D2qbAM7LO6jz');
define('STRIPE_API_BASE',        'https://api.stripe.com/v1');

// VIP Access Code — share with special clients to bypass payment
define('VIP_ACCESS_CODE', 'amazing123');

function stripeRequest($endpoint, $params = [], $method = 'POST') {
    $ch = curl_init(STRIPE_API_BASE . $endpoint);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 30,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

function stripeGet($endpoint) {
    $ch = curl_init(STRIPE_API_BASE . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}
