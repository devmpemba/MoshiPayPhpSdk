<?php

declare(strict_types=1);

namespace MoshiPay;

use MoshiPay\Exception\ValidationException;

final class Webhooks
{
    public static function sign(string $rawBody, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    public static function verifySignature(
        string $rawBody,
        string $timestamp,
        string $signature,
        string $secret,
        int $toleranceSeconds = 300
    ): bool {
        if ($timestamp === '' || $signature === '' || $secret === '') {
            return false;
        }

        if ($toleranceSeconds > 0 && abs(time() - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(self::sign($rawBody, $timestamp, $secret), $signature);
    }

    /**
     * @return array<string, mixed>
     */
    public static function parsePayload(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new ValidationException('Webhook body is not valid JSON.');
        }

        return $payload;
    }
}
