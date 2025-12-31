<?php

declare(strict_types=1);

namespace VindiSdk;

class BillAffiliate
{
    public function __construct(
        public readonly int $affiliateId,
        public readonly float $amount,
        public readonly int $amountType
    ) {
    }

    public function toArray(): array
    {
        return [
            'affiliate_id' => $this->affiliateId,
            'amount' => $this->amount,
            'amount_type' => $this->amountType,
        ];
    }
}
