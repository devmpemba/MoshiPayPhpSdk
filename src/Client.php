<?php

declare(strict_types=1);

namespace MoshiPay;

use MoshiPay\Exception\ApiException;
use MoshiPay\Exception\MoshiPayException;
use MoshiPay\Exception\ValidationException;
use MoshiPay\Http\CurlTransport;
use MoshiPay\Http\TransportInterface;

final class Client
{
    private const VERSION = '0.1.0';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl,
        private readonly int $timeout = 30,
        private readonly ?TransportInterface $transport = null
    ) {
        if ($this->apiKey === '' || $this->apiSecret === '') {
            throw new ValidationException('MoshiPay API key and secret are required.');
        }
    }

    public static function sandbox(string $apiKey, string $apiSecret): self
    {
        return new self($apiKey, $apiSecret, 'https://sandbox.moshipay.co.tz');
    }

    public static function live(string $apiKey, string $apiSecret): self
    {
        return new self($apiKey, $apiSecret, 'https://api.moshipay.co.tz');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload, ?string $idempotencyKey = null): array
    {
        $this->validatePaymentPayload($payload);

        return $this->request('POST', '/api/v1/payments', $payload, $idempotencyKey);
    }

    /**
     * @param array<string, mixed> $customer
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function createMobileMoneyPayment(
        float|int $amount,
        string $currency,
        string $phoneNumber,
        array $customer,
        ?string $description = null,
        ?string $callbackUrl = null,
        ?string $returnUrl = null,
        array $metadata = [],
        ?string $idempotencyKey = null
    ): array {
        return $this->createPayment([
            'payment_type' => 'mobile_money',
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'description' => $description,
            'phone_number' => $phoneNumber,
            'customer' => $customer + ['mobile' => $phoneNumber],
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
            'metadata' => $metadata,
        ], $idempotencyKey);
    }

    /**
     * @param array<string, mixed> $customer
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function createCardPayment(
        int $amount,
        string $currency,
        array $customer,
        string $redirectUrl,
        string $cancelUrl,
        ?string $description = null,
        ?string $callbackUrl = null,
        array $metadata = [],
        ?string $idempotencyKey = null
    ): array {
        return $this->createPayment([
            'payment_type' => 'card',
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'description' => $description,
            'customer' => $customer,
            'redirect_url' => $redirectUrl,
            'cancel_url' => $cancelUrl,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ], $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentIdentifier): array
    {
        if ($paymentIdentifier === '') {
            throw new ValidationException('MoshiPay payment identifier is required.');
        }

        return $this->request('GET', '/api/v1/payments/' . rawurlencode($paymentIdentifier));
    }

    public function verifyWebhookSignature(string $rawBody, string $timestamp, string $signature, ?string $secret = null): bool
    {
        return Webhooks::verifySignature($rawBody, $timestamp, $signature, $secret ?? $this->apiSecret);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseWebhook(string $rawBody): array
    {
        return Webhooks::parsePayload($rawBody);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'moshipay-php/' . self::VERSION,
            'X-API-Key' => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
        ];

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $response = ($this->transport ?? new CurlTransport())->request(
            $method,
            rtrim($this->baseUrl, '/') . $path,
            $headers,
            $payload,
            $this->timeout
        );

        $decoded = $response->body === '' ? [] : json_decode($response->body, true);

        if (! is_array($decoded)) {
            throw new MoshiPayException('MoshiPay returned a non-JSON response.', $response->statusCode);
        }

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new ApiException(
                $this->errorMessage($decoded),
                $response->statusCode,
                $decoded
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePaymentPayload(array $payload): void
    {
        $errors = [];
        $paymentType = (string) ($payload['payment_type'] ?? $payload['type'] ?? 'mobile_money');

        if (! in_array($paymentType, ['mobile_money', 'card'], true)) {
            $errors['payment_type'] = 'Payment type must be mobile_money or card.';
        }

        if (! isset($payload['amount']) || ! is_numeric($payload['amount']) || (float) $payload['amount'] <= 0) {
            $errors['amount'] = 'Amount must be greater than zero.';
        }

        if (! isset($payload['currency']) || ! is_string($payload['currency']) || strlen($payload['currency']) !== 3) {
            $errors['currency'] = 'Currency must be a 3-letter ISO currency code.';
        }

        $customer = isset($payload['customer']) && is_array($payload['customer'])
            ? $payload['customer']
            : [];

        if ($customer === []) {
            $errors['customer'] = 'Customer details are required.';
        }

        if ($paymentType === 'mobile_money') {
            $phone = $payload['phone_number'] ?? ($customer['mobile'] ?? null);

            if (! is_string($phone) || $phone === '') {
                $errors['phone_number'] = 'Phone number is required for mobile money payments.';
            }
        }

        if ($paymentType === 'card') {
            foreach (['redirect_url', 'cancel_url'] as $field) {
                if (! isset($payload[$field]) || ! filter_var($payload[$field], FILTER_VALIDATE_URL)) {
                    $errors[$field] = $field . ' must be a valid URL for card payments.';
                }
            }

            foreach (['address', 'city', 'state', 'postcode', 'country'] as $field) {
                if (! isset($customer[$field]) || $customer[$field] === '') {
                    $errors['customer.' . $field] = 'Customer ' . $field . ' is required for card payments.';
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Payment payload is invalid.', $errors);
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function errorMessage(array $response): string
    {
        $message = $response['message'] ?? $response['error'] ?? null;

        return is_string($message) && $message !== ''
            ? $message
            : 'MoshiPay API request failed.';
    }
}
