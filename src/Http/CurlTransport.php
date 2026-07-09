<?php

declare(strict_types=1);

namespace MoshiPay\Http;

use MoshiPay\Exception\MoshiPayException;

final class CurlTransport implements TransportInterface
{
    public function request(string $method, string $url, array $headers = [], ?array $json = null, int $timeout = 30): Response
    {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new MoshiPayException('Unable to initialize cURL.');
        }

        $responseHeaders = [];
        $encodedBody = $json === null ? null : json_encode($json, JSON_UNESCAPED_SLASHES);

        if ($json !== null && $encodedBody === false) {
            throw new MoshiPayException('Unable to encode request body as JSON.');
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADERFUNCTION => function ($curl, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts = explode(':', trim($headerLine), 2);

                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $responseHeaders[$name][] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if ($encodedBody !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedBody);
        }

        if ($headers !== []) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array_map(
                static fn (string $name, string $value): string => $name . ': ' . $value,
                array_keys($headers),
                $headers
            ));
        }

        $body = curl_exec($curl);

        if ($body === false) {
            $message = curl_error($curl) ?: 'MoshiPay request failed.';
            curl_close($curl);

            throw new MoshiPayException($message);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return new Response($statusCode, (string) $body, $responseHeaders);
    }
}
