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
        $req = new BankRequest(
            amount: 200.0,
            currency: 'BRL',
            customer: $customer,
            description: 'Boleto',
            dueDate: new DateTime('2099-01-01'),
            number: 'BOL123'
        );
        $res = $client->generateBank($req);

        $this->assertSame('b777', $res->tid);
        $this->assertSame(200.0, $res->amount);
        $this->assertSame('BRL', $res->currency);
        $this->assertNotEmpty($res->digitableLine);
        $this->assertNotEmpty($res->barCode);
    }
}
