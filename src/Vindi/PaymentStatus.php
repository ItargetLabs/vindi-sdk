<?php

declare(strict_types=1);

namespace VindiSdk;

enum PaymentStatus: string
{
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
}
