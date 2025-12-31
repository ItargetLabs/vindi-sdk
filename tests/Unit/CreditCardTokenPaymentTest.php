<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use VindiSdk\CreditCard\CreditCardClient;
use VindiSdk\Environment;
use VindiSdk\Store;

class CreditCardTokenPaymentTest extends TestCase
{
    public function testProcessTokenPaymentWithAffiliates(): void
    {
        $responses = new MockHandler([
            new Response(200, [], json_encode(['payment_profile' => ['id' => 77]])),
            new Response(200, [], json_encode(['bill' => ['id' => 'btok', 'status' => 'pending']]))
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

        $affiliates = [new \VindiSdk\BillAffiliate(2425, 50.0, 2)];
        $res = $client->processTokenPayment('tok_abc', 123.45, $affiliates, 'Pagamento token');

        $this->assertSame('btok', $res->tid);

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
