<?php

require __DIR__ . '/../vendor/autoload.php';

use MoshiPay\Client;

$client = new Client(
    apiKey: getenv('MOSHIPAY_API_KEY') ?: 'mp_live_xxx',
    apiSecret: getenv('MOSHIPAY_API_SECRET') ?: 'mps_xxx',
    baseUrl: getenv('MOSHIPAY_BASE_URL') ?: 'https://api.moshipay.co.tz'
);

$payment = $client->createMobileMoneyPayment(
    amount: 5000,
    currency: 'TZS',
    phoneNumber: '255781000000',
    customer: [
        'firstname' => 'Jane',
        'lastname' => 'Customer',
        'email' => 'jane@example.com',
    ],
    description: 'Order ORD-1001',
    callbackUrl: 'https://merchant.example.com/webhooks/moshipay',
    returnUrl: 'https://merchant.example.com/orders/ORD-1001',
    metadata: [
        'order_id' => 'ORD-1001',
    ],
    idempotencyKey: 'ORD-1001'
);

print_r($payment);
