<?php

declare(strict_types=1);

namespace VindiSdk;

class Customer
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $document = null,
        public ?string $phone = null,
        public ?Address $address = null
    ) {
    }
}
