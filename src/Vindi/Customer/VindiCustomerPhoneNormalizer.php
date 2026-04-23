<?php

declare(strict_types=1);

namespace VindiSdk\Customer;

/**
 * Regras puras: parsing BR → E.164 e montagem do payload `phones` da API Vindi.
 */
final class VindiCustomerPhoneNormalizer
{
    public const MAX_PHONES = 3;

    /**
     * @return array{type: string, number: string}|null
     */
    public static function parse(?string $raw): ?array
    {
        if (!$raw) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }
        if (!str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }
        if (strlen($digits) === 13 && substr($digits, 4, 1) === '9') {
            return ['number' => $digits, 'type' => 'mobile'];
        }
        if (strlen($digits) === 12) {
            return ['number' => $digits, 'type' => 'landline'];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $vindiCustomer
     * @return list<array{id: int, phone_type: string, number: string}>
     */
    public static function rowsWithIdFromCustomer(array $vindiCustomer): array
    {
        $rows = [];
        foreach ((array) ($vindiCustomer['phones'] ?? []) as $row) {
            $normalized = self::normalizeVindiPhoneRow($row);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }
        return $rows;
    }

    /**
     * @return array{id: int, phone_type: string, number: string}|null
     */
    public static function normalizeVindiPhoneRow(mixed $row): ?array
    {
        if (!is_array($row) || !is_numeric($row['id'] ?? null)) {
            return null;
        }
        $number = (string) ($row['number'] ?? '');
        if ($number === '') {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'phone_type' => (string) ($row['phone_type'] ?? 'mobile'),
            'number' => $number,
        ];
    }

    /**
     * @param list<array{id: int, phone_type: string, number: string}> $rows
     */
    public static function findRowByE164(array $rows, string $e164): ?array
    {
        foreach ($rows as $row) {
            if ($row['number'] === $e164) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param list<array{id: int, phone_type: string, number: string}> $rows
     */
    public static function mustClearAllSlotsToAddNew(array $rows, string $e164): bool
    {
        return count($rows) === self::MAX_PHONES && self::findRowByE164($rows, $e164) === null;
    }

    /**
     * @param list<array{id: int, phone_type: string, number: string}> $rows
     * @return list<array{id: int, _destroy: string}>
     */
    public static function destructionBatchForFirstRows(array $rows): array
    {
        $batch = [];
        foreach (array_slice($rows, 0, self::MAX_PHONES) as $row) {
            $batch[] = ['id' => $row['id'], '_destroy' => '1'];
        }
        return $batch;
    }

    /**
     * @param array{type: string, number: string} $parsed
     * @param array<string, mixed> $vindiCustomer
     * @return list<array<string, mixed>>
     */
    public static function buildPutPhonesPayload(array $vindiCustomer, array $parsed): array
    {
        $rows = self::rowsWithIdFromCustomer($vindiCustomer);
        $match = self::findRowByE164($rows, $parsed['number']);
        if ($match !== null) {
            return [self::writeBody($parsed['type'], $parsed['number'], $match['id'])];
        }
        if ($rows === []) {
            return [self::writeBody($parsed['type'], $parsed['number'])];
        }
        return self::mergeNewPhoneWithExistingRows($parsed, $rows);
    }

    /**
     * @param array{type: string, number: string} $parsed
     * @param list<array{id: int, phone_type: string, number: string}> $rows
     * @return list<array<string, mixed>>
     */
    public static function mergeNewPhoneWithExistingRows(array $parsed, array $rows): array
    {
        $phones = [self::writeBody($parsed['type'], $parsed['number'])];
        $seen = [$parsed['number'] => true];
        foreach ($rows as $row) {
            if (count($phones) >= self::MAX_PHONES) {
                break;
            }
            if (isset($seen[$row['number']])) {
                continue;
            }
            $seen[$row['number']] = true;
            $phones[] = self::writeBody($row['phone_type'], $row['number'], $row['id']);
        }
        return $phones;
    }

    /**
     * @return array<string, mixed>
     */
    public static function writeBody(string $phoneType, string $e164, ?int $existingId = null): array
    {
        $body = [
            'phone_type' => $phoneType,
            'number' => $e164,
        ];
        if ($existingId !== null) {
            $body['id'] = $existingId;
        }
        return $body;
    }
}
