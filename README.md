# MoshiPay PHP SDK

Production-ready PHP SDK for accepting payments through the MoshiPay payment gateway.

The SDK provides:

- Mobile money payment creation
- Card payment creation with redirect and cancel URLs
- Payment status lookup
- Idempotency support for payment creation
- Webhook signature verification and JSON parsing
- Typed exceptions for validation and API errors

## Requirements

- PHP 8.1 or newer
- PHP `curl` extension
- PHP `json` extension
- Composer

## Installation

```bash
composer require salymdev/moshipay-php
```

For local development before publishing to Packagist:

```bash
composer config repositories.moshipay-php path ../moshipay-php-sdk
composer require salymdev/moshipay-php:@dev
```

## Configuration

Store credentials in environment variables. Do not hard-code API keys, API secrets, or webhook secrets in source code.

```bash
MOSHIPAY_API_KEY=your_api_key
MOSHIPAY_API_SECRET=your_api_secret
MOSHIPAY_ENV=sandbox
```

Create a client for sandbox or live payments:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use MoshiPay\Client;

$apiKey = getenv('MOSHIPAY_API_KEY') ?: '';
$apiSecret = getenv('MOSHIPAY_API_SECRET') ?: '';

$client = getenv('MOSHIPAY_ENV') === 'live'
    ? Client::live($apiKey, $apiSecret)
    : Client::sandbox($apiKey, $apiSecret);
```

If you need to override the API base URL, instantiate the client directly:

```php
$client = new Client(
    apiKey: $apiKey,
    apiSecret: $apiSecret,
    baseUrl: getenv('MOSHIPAY_BASE_URL') ?: 'https://sandbox.moshipay.co.tz',
    timeout: 30
);
```

## Mobile Money Payments

```php
<?php

use MoshiPay\Exception\ApiException;
use MoshiPay\Exception\ValidationException;

try {
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

    $moshipayReference = $payment['moshipay_reference'] ?? null;
} catch (ValidationException $exception) {
    // Local payload validation failed before the API request was sent.
    $errors = $exception->errors();
} catch (ApiException $exception) {
    // MoshiPay returned a non-2xx response.
    $statusCode = $exception->statusCode();
    $response = $exception->response();
}
```

## Card Payments

```php
<?php

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

if (! empty($payment['payment_url'])) {
    header('Location: ' . $payment['payment_url'], true, 302);
    exit;
}
```

## Generic Payment Payloads

Use `createPayment()` when you need direct control over the request payload.

```php
<?php

$payment = $client->createPayment([
    'payment_type' => 'mobile_money',
    'amount' => 5000,
    'currency' => 'TZS',
    'phone_number' => '255781000000',
    'customer' => [
        'firstname' => 'Jane',
        'lastname' => 'Customer',
        'email' => 'jane@example.com',
    ],
    'callback_url' => 'https://merchant.example.com/webhooks/moshipay',
    'return_url' => 'https://merchant.example.com/orders/ORD-1001',
    'metadata' => [
        'order_id' => 'ORD-1001',
    ],
], 'ORD-1001');
```

Supported `payment_type` values are `mobile_money` and `card`.

## Fetch Payment Status

```php
<?php

$payment = $client->getPayment('MP-260709-ABCDEFGH');
```

Pass the MoshiPay payment identifier returned by a create payment response.

## Webhooks

MoshiPay signs callbacks with these headers:

- `X-MoshiPay-Event`
- `X-MoshiPay-Reference`
- `X-MoshiPay-Timestamp`
- `X-MoshiPay-Signature`

The signature is generated as:

```text
HMAC_SHA256(timestamp + "." + raw_body, webhook_secret)
```

For merchant callback URLs that use your account credentials, the default verification secret is the API secret configured on the client. If your MoshiPay dashboard provides a separate webhook endpoint secret, pass it as the fourth argument to `verifyWebhookSignature()`.

```php
<?php

http_response_code(200);

$rawBody = file_get_contents('php://input') ?: '';
$timestamp = $_SERVER['HTTP_X_MOSHIPAY_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_MOSHIPAY_SIGNATURE'] ?? '';
$webhookSecret = getenv('MOSHIPAY_WEBHOOK_SECRET') ?: null;

if (! $client->verifyWebhookSignature($rawBody, $timestamp, $signature, $webhookSecret)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}

$payload = $client->parseWebhook($rawBody);
$event = $_SERVER['HTTP_X_MOSHIPAY_EVENT'] ?? null;
$reference = $_SERVER['HTTP_X_MOSHIPAY_REFERENCE'] ?? ($payload['moshipay_reference'] ?? null);

// Look up your local order using metadata or reference, then update it idempotently.
// Return a 2xx response only after your handler has completed successfully.
```

Webhook verification includes a default timestamp tolerance of 300 seconds to reduce replay risk.

## Error Handling

```php
<?php

use MoshiPay\Exception\ApiException;
use MoshiPay\Exception\MoshiPayException;
use MoshiPay\Exception\ValidationException;

try {
    $payment = $client->createMobileMoneyPayment(
        amount: 5000,
        currency: 'TZS',
        phoneNumber: '255781000000',
        customer: ['firstname' => 'Jane'],
        idempotencyKey: 'ORD-1001'
    );
} catch (ValidationException $exception) {
    foreach ($exception->errors() as $field => $message) {
        // Handle invalid local input.
    }
} catch (ApiException $exception) {
    $statusCode = $exception->statusCode();
    $apiResponse = $exception->response();
} catch (MoshiPayException $exception) {
    // Non-JSON responses and other SDK-level failures.
}
```

## Idempotency

Pass a stable `idempotencyKey` when creating payments. A good key is your internal order ID or payment attempt ID.

```php
$payment = $client->createMobileMoneyPayment(
    amount: 5000,
    currency: 'TZS',
    phoneNumber: '255781000000',
    customer: ['firstname' => 'Jane'],
    idempotencyKey: 'ORDER-1001-PAYMENT-1'
);
```

Use a new idempotency key for a genuinely new payment attempt.

## Production Checklist

- Use `Client::sandbox()` while testing and `Client::live()` only after credentials are approved for production.
- Keep API keys, API secrets, and webhook secrets in environment variables or a secret manager.
- Always pass an idempotency key when creating payments from an order or invoice.
- Validate and persist your own order state before redirecting customers or returning webhook success responses.
- Treat webhooks as the source of truth for final payment state.
- Make webhook handlers idempotent because callbacks may be retried.
- Verify webhook signatures using the raw request body before parsing JSON.
- Log API status codes and response bodies for failed requests, but never log secrets.
- Use HTTPS URLs for callback, return, redirect, and cancel URLs.

## Testing

Install development dependencies:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

Validate package metadata:

```bash
composer validate --strict
```

## API Coverage

Implemented:

- Create mobile money payments
- Create card payments
- Fetch payment status
- Idempotency key header
- Webhook signature verification
- Webhook JSON parsing

Not implemented yet:

- Refunds
- Settlements
- Payouts
- Disputes

## License

MIT. See [LICENSE](LICENSE).
