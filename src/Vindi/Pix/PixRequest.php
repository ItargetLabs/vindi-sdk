<?php

declare(strict_types=1);

namespace VindiSdk\Pix;

use VindiSdk\Customer;

class PixRequest
{
    public function __construct(
        public float $amount,
        public string $currency,
        public Customer $customer,
        public ?string $description = null,
        public array $affiliates = []
    ) {
    }
}
