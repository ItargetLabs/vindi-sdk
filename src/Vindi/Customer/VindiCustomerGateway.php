<?php

declare(strict_types=1);

namespace VindiSdk\Customer;

/**
 * Porta mínima para operações de cliente usadas pelo fluxo de telefones (DIP).
 */
interface VindiCustomerGateway
{
    /**
     * @return array<string, mixed>
     */
    public function fetchById(int $customerId): array;

    /**
     * @param array<string, mixed> $body
     */
    public function update(int $customerId, array $body): void;
}
