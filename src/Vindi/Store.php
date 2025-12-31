<?php

declare(strict_types=1);

namespace VindiSdk;

class Store
{
    public function __construct(
        private string $publicKey,
        private string $privateKey,
        private Environment $environment
    ) {
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
}
