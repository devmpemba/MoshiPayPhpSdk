# MoshiPay PHP SDK

PHP SDK for integrating merchant systems with the MoshiPay payment gateway.

## Requirements

- PHP 8.1 or newer
- `ext-curl`
- `ext-json`

## Installation

```bash
composer require salymdev/moshipay-php
```

For local development before publishing to Packagist:

```bash
composer config repositories.moshipay-php path ../moshipay-php-sdk
composer require salymdev/moshipay-php:@dev
```

## Client Setup

```php
use MoshiPay\Client;

$client = new Client(
    apiKey: getenv('MOSHIPAY_API_KEY'),
    apiSecret: getenv('MOSHIPAY_API_SECRET'),
    baseUrl: getenv('MOSHIPAY_BASE_URL') ?: 'https://sandbox.moshipay.co.tz'
);
```

## Mobile Money Payment

```php
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
```

## Card Payment

```php
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
```

## Fetch Payment Status

```php
$payment = $client->getPayment('MP-260709-ABCDEFGH');
```

Pass the MoshiPay payment identifier returned by `createPayment()`. In the current MoshiPay backend this route is `/api/v1/payments/{payment}`.

## Webhook Verification

MoshiPay sends callbacks with these headers:

- `X-MoshiPay-Event`
- `X-MoshiPay-Reference`
- `X-MoshiPay-Timestamp`
- `X-MoshiPay-Signature`

The signature is:

```text
HMAC_SHA256(timestamp + "." + raw_body, webhook_secret)
```

For request-level callbacks and merchant default callback URLs, the webhook secret is currently the merchant API secret. For configured Developer webhook endpoints, use the endpoint secret shown in MoshiPay.

```php
$rawBody = file_get_contents('php://input') ?: '';
$timestamp = $_SERVER['HTTP_X_MOSHIPAY_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_MOSHIPAY_SIGNATURE'] ?? '';

if (! $client->verifyWebhookSignature($rawBody, $timestamp, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$payload = $client->parseWebhook($rawBody);
```

## Generic Payment Payload

If you need full control, use `createPayment()`:

```php
$payment = $client->createPayment([
    'payment_type' => 'mobile_money',
    'amount' => 5000,
    'currency' => 'TZS',
    'phone_number' => '255781000000',
    'customer' => [
        'firstname' => 'Jane',
        'lastname' => 'Customer',
    ],
    'callback_url' => 'https://merchant.example.com/webhooks/moshipay',
    'metadata' => [
        'order_id' => 'ORD-1001',
    ],
], 'ORD-1001');
```

## Error Handling

```php
use MoshiPay\Exception\ApiException;
use MoshiPay\Exception\ValidationException;

try {
    $payment = $client->createMobileMoneyPayment(...);
} catch (ValidationException $exception) {
    var_dump($exception->errors());
} catch (ApiException $exception) {
    echo $exception->statusCode();
    var_dump($exception->response());
}
```

## Testing

```bash
composer install
composer test
```

## Current API Coverage

- Create mobile money payments
- Create card payments
- Fetch payment status
- Idempotency key header
- Merchant callback signature verification

Refunds, settlements, payout APIs, and disputes are not exposed in this SDK yet because the MoshiPay public merchant API does not expose those endpoints yet.
