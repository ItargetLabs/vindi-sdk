<?php

declare(strict_types=1);

namespace VindiSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VindiSdk\Environment;

class EnvironmentTest extends TestCase
{
    public function testSandboxUrl(): void
    {
        $env = Environment::sandbox();
        $this->assertSame('https://sandbox-app.vindi.com.br/api/v1/', $env->getApiUrl());
    }

    public function testProductionUrl(): void
    {
        $env = Environment::production();
        $this->assertSame('https://app.vindi.com.br/api/v1/', $env->getApiUrl());
    }
}
