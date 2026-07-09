<?php

declare(strict_types=1);

namespace MoshiPay\Tests;

use MoshiPay\Http\Response;
use MoshiPay\Http\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /**
     * @var list<array{method: string, url: string, headers: array<string, string>, json: array<string, mixed>|null, timeout: int}>
     */
    public array $requests = [];

    public function __construct(private readonly Response $response)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?array $json = null, int $timeout = 30): Response
    {
        $this->requests[] = compact('method', 'url', 'headers', 'json', 'timeout');

        return $this->response;
    }
}
