<?php

declare(strict_types=1);

namespace VindiSdk;

class Environment
{
    private function __construct(private string $apiUrl)
    {
    }

    public static function production(): self
    {
        return new self('https://app.vindi.com.br/api/v1/');
    }

    public static function sandbox(): self
    {
        return new self('https://sandbox-app.vindi.com.br/api/v1/');
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }
}
