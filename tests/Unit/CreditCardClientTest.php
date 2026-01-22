<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use VindiSdk\Customer;
use VindiSdk\Address;
use VindiSdk\Environment;
use VindiSdk\CreditCard\CreditCard;
use VindiSdk\CreditCard\CreditCardClient;
use VindiSdk\CreditCard\CreditCardRequest;
use VindiSdk\Store;

class CreditCardClientTest extends TestCase
{
    public function testProcessCreditCardPayment(): void
    {
        $responses = new MockHandler([
            new Response(200, [], json_encode(['customers' => []])),
            new Response(200, [], json_encode(['customer' => ['id' => 1]])),
            new Response(200, [], json_encode(['payment_profiles' => []])),
            new Response(200, [], json_encode(['payment_profile' => ['gateway_token' => 'tok123']])),
            new Response(200, [], json_encode(['payment_profile' => ['id' => 77]])),
            new Response(200, [], json_encode([
                'bill' => [
                    'id' => 'b999',
                    'status' => 'pending'
                ]
            ])),
        ]);
        $handlerStack = HandlerStack::create($responses);
        $history = [];
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack, 'base_uri' => Environment::sandbox()->getApiUrl()]);

        $store = new Store('pub', 'priv', Environment::sandbox());
        $client = new CreditCardClient($store, 363801);

        $refClient = new \ReflectionClass(CreditCardClient::class);
        $baseCtor = $refClient->getParentClass()->getConstructor();
        $baseCtor->invoke($client, $store, $httpClient);

        $customer = new Customer(
            id: 'C1',
            name: 'Nome',
            email: 'email@ex.com',
            document: '12345678900',
            phone: '11999999999',
            address: new Address('Rua', '1', '01234567', 'Bairro', 'SP', 'SP')
        );
        $card = new CreditCard('4111111111111111', 'Cliente', '12', '2028', '123', 'visa');
        $affiliates = [new \VindiSdk\BillAffiliate(2425, 50.0, 2)];
        $req = new CreditCardRequest(
            amount: 150.0,
            currency: 'BRL',
            customer: $customer,
            creditCard: $card,
            installments: 1,
            description: 'Teste',
            affiliates: $affiliates
        );
        $res = $client->processPayment($req);

        $this->assertSame('b999', $res->tid);
        $this->assertSame(150.0, $res->amount);
        $this->assertSame('BRL', $res->currency);
        $this->assertNull($res->nsu);
        $billPosts = array_filter(
            $history,
            static function ($h) {
                return (string) $h['request']->getMethod() === 'POST'
                    && str_contains((string) $h['request']->getUri()->getPath(), 'bills');
            }
        );
        $last = array_values($billPosts)[0] ?? null;
        $this->assertNotNull($last);
        $payload = json_decode((string) $last['request']->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('bill_affiliates', $payload);
        $this->assertSame(2425, $payload['bill_affiliates'][0]['affiliate_id']);
        $this->assertSame(50.0, (float) $payload['bill_affiliates'][0]['amount']);
        $this->assertSame(2, $payload['bill_affiliates'][0]['amount_type']);
    }

    public function testProcessCreditCardPaymentWithInstallments(): void
    {
        $responses = new MockHandler([
            new Response(200, [], json_encode(['customers' => []])),
            new Response(200, [], json_encode(['customer' => ['id' => 2]])),
            new Response(200, [], json_encode(['payment_profiles' => []])),
            new Response(200, [], json_encode(['payment_profile' => ['gateway_token' => 'tok789']])),
            new Response(200, [], json_encode(['payment_profile' => ['id' => 88]])),
            new Response(200, [], json_encode([
                'bill' => [
                    'id' => 'b1000',
                    'status' => 'pending'
                ]
            ])),
        ]);
        $handlerStack = HandlerStack::create($responses);
        $history = [];
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack, 'base_uri' => Environment::sandbox()->getApiUrl()]);

        $store = new Store('pub', 'priv', Environment::sandbox());
        $client = new CreditCardClient($store, 363801);

        $refClient = new \ReflectionClass(CreditCardClient::class);
        $baseCtor = $refClient->getParentClass()->getConstructor();
        $baseCtor->invoke($client, $store, $httpClient);

        $customer = new Customer(
            id: 'C2',
            name: 'Nome',
            email: 'email@ex.com',
            document: '12345678900',
            phone: '11999999999',
            address: new Address('Rua', '1', '01234567', 'Bairro', 'SP', 'SP')
        );
        $card = new CreditCard('4111111111111111', 'Cliente', '12', '2028', '123', 'visa');
        $req = new CreditCardRequest(
            amount: 300.0,
            currency: 'BRL',
            customer: $customer,
            creditCard: $card,
            installments: 3,
            description: 'Teste parcelas'
        );
        $res = $client->processPayment($req);

        $this->assertSame('b1000', $res->tid);
        $this->assertSame(3, $res->installments);
        $this->assertSame(100.0, $res->installmentAmount);

        $billPosts = array_filter(
            $history,
            static function ($h) {
                return (string) $h['request']->getMethod() === 'POST'
                    && str_contains((string) $h['request']->getUri()->getPath(), 'bills');
            }
        );
        $last = array_values($billPosts)[0] ?? null;
        $this->assertNotNull($last);
        $payload = json_decode((string) $last['request']->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('installments', $payload);
        $this->assertSame(3, (int) $payload['installments']);
    }
}
