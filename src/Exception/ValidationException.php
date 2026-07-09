<?php

declare(strict_types=1);

namespace MoshiPay\Exception;

class ValidationException extends MoshiPayException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
