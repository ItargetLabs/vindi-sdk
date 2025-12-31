<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use GuzzleHttp\Client;
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
        $req = new CreditCardRequest(
            amount: 150.0,
            currency: 'BRL',
            customer: $customer,
            creditCard: $card,
            installments: 1,
            description: 'Teste'
        );
        $res = $client->processPayment($req);

        $this->assertSame('b999', $res->tid);
        $this->assertSame(150.0, $res->amount);
        $this->assertSame('BRL', $res->currency);
        $this->assertNull($res->nsu);
    }
}
