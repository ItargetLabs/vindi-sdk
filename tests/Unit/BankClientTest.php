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
use VindiSdk\Bank\BankClient;
use VindiSdk\Bank\BankRequest;
use VindiSdk\Store;
use DateTime;

class BankClientTest extends TestCase
{
    public function testGenerateBank(): void
    {
        $responses = new MockHandler([
            new Response(200, [], json_encode(['customers' => []])),
            new Response(200, [], json_encode(['customer' => ['id' => 1]])),
            new Response(200, [], json_encode([
                'bill' => [
                    'id' => 'b777',
                    'status' => 'open',
                    'charges' => [[
                        'digitable_line' => '00190.00009 01234.567890 12345.678901 2 12340000010000',
                        'bar_code' => '00191234567890123456789012345678901234567890',
                        'print_url' => 'https://vindi/print/b777'
                    ]],
                    'due_at' => '2099-01-01'
                ]
            ])),
        ]);
        $handlerStack = HandlerStack::create($responses);
        $history = [];
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack, 'base_uri' => Environment::sandbox()->getApiUrl()]);

        $store = new Store('pub', 'priv', Environment::sandbox());
        $client = new BankClient($store, 363801);

        $refClient = new \ReflectionClass(BankClient::class);
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
        $affiliates = [new \VindiSdk\BillAffiliate(2425, 50.0, 2)];
        $req = new BankRequest(
            amount: 200.0,
            currency: 'BRL',
            customer: $customer,
            description: 'Boleto',
            dueDate: new DateTime('2099-01-01'),
            number: 'BOL123',
            affiliates: $affiliates
        );
        $res = $client->generateBank($req);

        $this->assertSame('b777', $res->tid);
        $this->assertSame(200.0, $res->amount);
        $this->assertSame('BRL', $res->currency);
        $this->assertNotEmpty($res->digitableLine);
        $this->assertNotEmpty($res->barCode);
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
}
