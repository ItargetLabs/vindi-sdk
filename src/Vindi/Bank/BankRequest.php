<?php

declare(strict_types=1);

namespace VindiSdk\Bank;

use DateTime;
use VindiSdk\Customer;

class BankRequest
{
    public function __construct(
        public float $amount,
        public string $currency,
        public Customer $customer,
        public string $description,
        public DateTime $dueDate,
        public ?string $number = null,
        public array $metadata = []
    ) {
    }
}
