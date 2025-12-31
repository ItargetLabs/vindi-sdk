<?php

declare(strict_types=1);

namespace VindiSdk\CreditCard;

use VindiSdk\Customer;

class CreditCardRequest
{
    public function __construct(
        public float $amount,
        public string $currency,
        public Customer $customer,
        public CreditCard $creditCard,
        public ?int $installments = null,
        public ?string $description = null,
        public array $metadata = []
    ) {
    }
}
