<?php

require __DIR__ . '/../vendor/autoload.php';

use MoshiPay\Client;

$rawBody = file_get_contents('php://input') ?: '';
$timestamp = $_SERVER['HTTP_X_MOSHIPAY_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_MOSHIPAY_SIGNATURE'] ?? '';

$client = new Client(
    apiKey: getenv('MOSHIPAY_API_KEY') ?: 'mp_live_xxx',
    apiSecret: getenv('MOSHIPAY_API_SECRET') ?: 'mps_xxx',
    baseUrl: getenv('MOSHIPAY_BASE_URL') ?: 'https://api.moshipay.co.tz'
);

if (! $client->verifyWebhookSignature($rawBody, $timestamp, $signature)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}

$payload = $client->parseWebhook($rawBody);

if (($payload['event'] ?? '') === 'payment.completed') {
    $orderId = $payload['metadata']['order_id'] ?? null;
    $reference = $payload['moshipay_reference'] ?? null;

    // Mark your local order as paid using $orderId and $reference.
}

http_response_code(200);
echo 'ok';
