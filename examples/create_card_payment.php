<?php

require __DIR__ . '/../vendor/autoload.php';

use MoshiPay\Client;

$client = new Client(
    apiKey: getenv('MOSHIPAY_API_KEY') ?: 'mp_live_xxx',
    apiSecret: getenv('MOSHIPAY_API_SECRET') ?: 'mps_xxx',
    baseUrl: getenv('MOSHIPAY_BASE_URL') ?: 'https://api.moshipay.co.tz'
);

$payment = $client->createCardPayment(
    amount: 10000,
    currency: 'TZS',
    customer: [
        'firstname' => 'Jane',
        'lastname' => 'Customer',
        'email' => 'jane@example.com',
        'mobile' => '255781000000',
        'address' => 'Customer Address',
        'city' => 'Dar es Salaam',
        'state' => 'DSM',
        'postcode' => '14101',
        'country' => 'TZ',
    ],
    redirectUrl: 'https://merchant.example.com/payment/success',
    cancelUrl: 'https://merchant.example.com/payment/cancel',
    description: 'Order ORD-1002',
    callbackUrl: 'https://merchant.example.com/webhooks/moshipay',
    metadata: [
        'order_id' => 'ORD-1002',
    ],
    idempotencyKey: 'ORD-1002'
);

header('Location: ' . $payment['payment_url']);
