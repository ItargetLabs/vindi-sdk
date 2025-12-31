<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use DateTime;
use GuzzleHttp\Middleware;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use VindiSdk\Customer;
use VindiSdk\Address;
use VindiSdk\Environment;
use VindiSdk\Pix\PixClient;
use VindiSdk\Pix\PixRequest;
use VindiSdk\Store;

class PixClientTest extends TestCase
{
    public function testGeneratePixCharge(): void
    {
        $responses = new MockHandler([
            new Response(200, [], json_encode(['customers' => []])),
            new Response(200, [], json_encode(['customer' => ['id' => 1]])),
            new Response(200, [], json_encode([
                'bill' => [
                    'id' => 'b123',
                    'status' => 'paid',
                    'charges' => [[
                        'last_transaction' => [
                            'status' => 'approved',
                            'gateway_response_fields' => [
                                'qrcode_original_path' => '0002010102122689PIXCODE',
                                'transaction_id' => 't789',
                                'max_days_to_keep_waiting_payment' => '2099-01-01T00:00:00Z'
                            ]
                        ]
                    ]]
                ]
            ])),
        ]);
        $handlerStack = HandlerStack::create($responses);
        $history = [];
        $handlerStack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $handlerStack, 'base_uri' => Environment::sandbox()->getApiUrl()]);

        $store = new Store('pub', 'priv', Environment::sandbox());
        $client = new PixClient($store, 363801);

        $refClient = new \ReflectionClass(PixClient::class);
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
        $req = new PixRequest(
            amount: 100.0,
            currency: 'BRL',
            customer: $customer,
            description: 'PIX',
            affiliates: $affiliates
        );
        $res = $client->generatePixCharge($req);

        $this->assertSame('b123', $res->tid);
        $this->assertSame(100.0, $res->amount);
        $this->assertSame('BRL', $res->currency);
        $this->assertSame('t789', $res->pixId);
        $this->assertNotEmpty($res->qrCode);
        $this->assertSame('0002010102122689PIXCODE', $res->qrCodeText);
        $this->assertSame('0002010102122689PIXCODE', $res->pixCopyPaste);
        $this->assertGreaterThan(0, $res->expiresInMinutes);
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
