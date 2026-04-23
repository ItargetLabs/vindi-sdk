<?php

declare(strict_types=1);

namespace VindiSdk\Customer;

/**
 * Garante que o cliente na Vindi tenha espaço para o telefone atual antes do PUT completo (acúmulo no PUT).
 */
final class VindiCustomerPhoneSlotSynchronizer
{
    public function __construct(
        private readonly VindiCustomerGateway $customers,
    ) {
    }

    /**
     * @param array{type: string, number: string} $parsed
     * @param array<string, mixed> $vindiCustomer
     * @return array<string, mixed>
     */
    public function ensureSlots(int $customerId, array $parsed, array $vindiCustomer): array
    {
        $e164 = $parsed['number'];
        $rows = VindiCustomerPhoneNormalizer::rowsWithIdFromCustomer($vindiCustomer);
        while (count($rows) > VindiCustomerPhoneNormalizer::MAX_PHONES) {
            $this->destroyFirstPhoneBatch($customerId, $rows);
            $vindiCustomer = $this->customers->fetchById($customerId);
            $rows = VindiCustomerPhoneNormalizer::rowsWithIdFromCustomer($vindiCustomer);
        }
        if (VindiCustomerPhoneNormalizer::mustClearAllSlotsToAddNew($rows, $e164)) {
            $this->destroyFirstPhoneBatch($customerId, $rows);
            $vindiCustomer = $this->customers->fetchById($customerId);
        }
        return $vindiCustomer;
    }

    /**
     * @param list<array{id: int, phone_type: string, number: string}> $rows
     */
    private function destroyFirstPhoneBatch(int $customerId, array $rows): void
    {
        $batch = VindiCustomerPhoneNormalizer::destructionBatchForFirstRows($rows);
        if ($batch === []) {
            return;
        }
        $this->customers->update($customerId, ['phones' => $batch]);
    }
}
