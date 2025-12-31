<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use DateTime;
use PHPUnit\Framework\TestCase;
use VindiSdk\Vindi;

class WebhookParseTest extends TestCase
{
    public function testParseSettlementWebhook(): void
    {
        $payload = [
            'event' => [
                'created_at' => '2099-01-01T00:00:00Z',
                'data' => [
                    'bill' => [
                        'id' => 'b101',
                        'status' => 'paid',
                        'charges' => [[
                            'status' => 'paid',
                            'payment_method' => ['code' => 'pix'],
                            'paid_at' => '2099-01-02T00:00:00Z',
                            'created_at' => '2099-01-01T00:00:00Z',
                            'last_transaction' => [
                                'gateway_response_fields' => [
                                    'transaction_id' => 't101'
                                ]
                            ]
                        ]]
                    ]
                ]
            ]
        ];

        $parsed = Vindi::parseSettlementWebhook($payload);
        $this->assertSame('b101', $parsed['tid']);
        $this->assertSame('t101', $parsed['transactionId']);
        $this->assertSame('pix', $parsed['paymentMethodCode']);
        $this->assertSame('paid', $parsed['statusCode']);
        $this->assertInstanceOf(DateTime::class, $parsed['lowDate']);
        $this->assertInstanceOf(DateTime::class, $parsed['occurrenceDate']);
    }
}
