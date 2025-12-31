<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VindiSdk\Environment;
use VindiSdk\Store;

class StoreTest extends TestCase
{
    public function testGetters(): void
    {
        $env = Environment::sandbox();
        $store = new Store('pub', 'priv', $env);
        $this->assertSame('pub', $store->getPublicKey());
        $this->assertSame('priv', $store->getPrivateKey());
        $this->assertSame($env, $store->getEnvironment());
    }
}
