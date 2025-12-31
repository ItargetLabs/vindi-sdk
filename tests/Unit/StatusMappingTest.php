<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use VindiSdk\Environment;
use VindiSdk\PaymentStatus;
use VindiSdk\Store;
use VindiSdk\VindiBaseClient;

class StatusMappingTest extends TestCase
{
    public function testMapVindiStatus(): void
    {
        $client = new VindiBaseClient(new Store('pub', 'priv', Environment::sandbox()), new Client());
        $this->assertSame(PaymentStatus::APPROVED, $client->mapVindiStatus('paid'));
        $this->assertSame(PaymentStatus::PENDING, $client->mapVindiStatus('pending'));
        $this->assertSame(PaymentStatus::PENDING, $client->mapVindiStatus('open'));
        $this->assertSame(PaymentStatus::CANCELLED, $client->mapVindiStatus('canceled'));
        $this->assertSame(PaymentStatus::CANCELLED, $client->mapVindiStatus('cancelled'));
        $this->assertSame(PaymentStatus::PENDING, $client->mapVindiStatus('unknown'));
    }
}
