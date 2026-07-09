<?php

declare(strict_types=1);

namespace MoshiPay\Exception;

class ApiException extends MoshiPayException
{
    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?array $response = null
    ) {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function response(): ?array
    {
        return $this->response;
    }
}
