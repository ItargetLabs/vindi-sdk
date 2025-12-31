<?php

declare(strict_types=1);

namespace VindiSdk;

class Config
{
    public static function createStoreFromEnv(): Store
    {
        $envName = getenv('VINDI_ENVIRONMENT') ?: 'sandbox';
        $publicKey = getenv('VINDI_PUBLIC_KEY') ?: '';
        $privateKey = getenv('VINDI_PRIVATE_KEY') ?: '';

        $environment = ($envName === 'production')
            ? Environment::production()
            : Environment::sandbox();

        return new Store($publicKey, $privateKey, $environment);
    }

    public static function createVindiFromEnv(): Vindi
    {
        $store = self::createStoreFromEnv();
        $productId = (int) (getenv('VINDI_PRODUCT_ID') ?: 0);
        return new Vindi($store, $productId);
    }
}
