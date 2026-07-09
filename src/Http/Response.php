<?php

declare(strict_types=1);

namespace MoshiPay\Http;

final class Response
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = []
    ) {
    }
}
