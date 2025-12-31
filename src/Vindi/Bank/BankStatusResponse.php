<?php

declare(strict_types=1);

namespace VindiSdk\Bank;

use DateTime;
use VindiSdk\PaymentStatus;

class BankStatusResponse
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly ?string $digitableLine,
        public readonly ?string $barCode,
        public readonly ?string $bankNumber,
        public readonly ?DateTime $dueDate,
        public readonly ?DateTime $issueDate,
        public readonly ?DateTime $occurrenceDate,
        public readonly array $rawResponse
    ) {
    }
}
