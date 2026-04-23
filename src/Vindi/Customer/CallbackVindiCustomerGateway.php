<?php

declare(strict_types=1);

namespace VindiSdk\Customer;

/**
 * Adapta o request JSON privado do {@see \VindiSdk\VindiBaseClient} à porta {@see VindiCustomerGateway}.
 *
 * @phpstan-type RequestJson callable(string, string, array, bool): array
 */
final class CallbackVindiCustomerGateway implements VindiCustomerGateway
{
    /**
     * @param \Closure(string, string, array, bool): array $requestPrivateJson
     */
    public function __construct(
        private readonly \Closure $requestPrivateJson,
    ) {
    }

    public function fetchById(int $customerId): array
    {
        $response = ($this->requestPrivateJson)('GET', 'customers/' . $customerId, [], true);
        return (array) ($response['customer'] ?? []);
    }

    public function update(int $customerId, array $body): void
    {
        ($this->requestPrivateJson)('PUT', 'customers/' . $customerId, $body, true);
    }
}
