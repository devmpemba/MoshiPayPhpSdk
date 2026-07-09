<?php

declare(strict_types=1);

namespace MoshiPay\Tests;

use MoshiPay\Client;
use MoshiPay\Exception\ApiException;
use MoshiPay\Exception\ValidationException;
use MoshiPay\Http\Response;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function test_it_creates_mobile_money_payment(): void
    {
        $transport = new FakeTransport(new Response(201, json_encode([
            'moshipay_reference' => 'MP-260709-ABCDEFGH',
            'status' => 'forwarded',
        ], JSON_THROW_ON_ERROR)));

        $client = new Client('mp_live_key', 'mps_secret', 'https://pay.example.test', 15, $transport);

        $response = $client->createMobileMoneyPayment(
            amount: 5000,
            currency: 'tzs',
            phoneNumber: '255781000000',
            customer: ['firstname' => 'Jane', 'lastname' => 'Customer'],
            description: 'Order ORD-100',
            callbackUrl: 'https://merchant.test/webhooks/moshipay',
            metadata: ['order_id' => 'ORD-100'],
            idempotencyKey: 'ORD-100'
        );

        $this->assertSame('MP-260709-ABCDEFGH', $response['moshipay_reference']);
        $this->assertCount(1, $transport->requests);
        $this->assertSame('POST', $transport->requests[0]['method']);
        $this->assertSame('https://pay.example.test/api/v1/payments', $transport->requests[0]['url']);
        $this->assertSame('mp_live_key', $transport->requests[0]['headers']['X-API-Key']);
        $this->assertSame('mps_secret', $transport->requests[0]['headers']['X-API-Secret']);
        $this->assertSame('ORD-100', $transport->requests[0]['headers']['Idempotency-Key']);
        $this->assertSame('mobile_money', $transport->requests[0]['json']['payment_type']);
        $this->assertSame('TZS', $transport->requests[0]['json']['currency']);
    }

    public function test_it_fetches_payment_by_reference(): void
    {
        $transport = new FakeTransport(new Response(200, '{"status":"completed"}'));
        $client = new Client('key', 'secret', 'https://pay.example.test', transport: $transport);

        $response = $client->getPayment('MP-260709-ABCDEFGH');

        $this->assertSame('completed', $response['status']);
        $this->assertSame('GET', $transport->requests[0]['method']);
        $this->assertSame('https://pay.example.test/api/v1/payments/MP-260709-ABCDEFGH', $transport->requests[0]['url']);
    }

    public function test_it_throws_api_exception_for_non_success_status(): void
    {
        $transport = new FakeTransport(new Response(422, '{"message":"Invalid payload"}'));
        $client = new Client('key', 'secret', 'https://pay.example.test', transport: $transport);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid payload');

        $client->createMobileMoneyPayment(1000, 'TZS', '255781000000', [
            'firstname' => 'Jane',
            'lastname' => 'Customer',
        ]);
    }

    public function test_it_validates_card_payload_before_request(): void
    {
        $transport = new FakeTransport(new Response(201, '{}'));
        $client = new Client('key', 'secret', 'https://pay.example.test', transport: $transport);

        try {
            $client->createCardPayment(1000, 'TZS', [
                'firstname' => 'Jane',
                'lastname' => 'Customer',
            ], 'https://merchant.test/done', 'https://merchant.test/cancel');
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer.address', $exception->errors());
            $this->assertCount(0, $transport->requests);
        }
    }

    public function test_it_verifies_webhook_signature(): void
    {
        $body = '{"event":"payment.completed"}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'mps_secret');

        $client = new Client('key', 'mps_secret', 'https://pay.example.test');

        $this->assertTrue($client->verifyWebhookSignature($body, $timestamp, $signature));
        $this->assertFalse($client->verifyWebhookSignature($body, $timestamp, 'bad-signature'));
    }
}
