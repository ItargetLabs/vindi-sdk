<?php

declare(strict_types=1);

namespace VindiSdk;

use DateTime;
use DomainException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class VindiBaseClient
{
    private Client $httpClient;
    private Store $store;

    public function __construct(Store $store, ?Client $httpClient = null)
    {
        $this->store = $store;
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->store->getEnvironment()->getApiUrl(),
            'timeout' => 30,
        ]);
    }

    protected function requestJson(string $method, string $path, array $payload, bool $usePrivate): array
    {
        try {
            $key = $usePrivate ? $this->store->getPrivateKey() : $this->store->getPublicKey();
            $auth = 'Basic ' . base64_encode($key . ':');
            $options = [
                'headers' => [
                    'Authorization' => $auth,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ];
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $options['json'] = $payload;
            }
            $response = $this->httpClient->request($method, $path, $options);
            $contents = (string) $response->getBody();
            $decoded = json_decode($contents, true);
            return is_array($decoded) ? $decoded : [];
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getBill(string $id): array
    {
        $response = $this->requestJson('GET', 'bills/' . urlencode($id), [], true);
        return (array) ($response['bill'] ?? []);
    }

    public static function parseSettlementWebhook(array $payload): array
    {
        $event = (array) ($payload['event'] ?? []);
        $bill = (array) (($event['data']['bill'] ?? []) ?: []);
        $charges = (array) ($bill['charges'] ?? []);
        $charge = (array) ($charges[0] ?? []);
        $lastTransaction = (array) ($charge['last_transaction'] ?? []);
        $gwFields = (array) ($lastTransaction['gateway_response_fields'] ?? []);

        $paidAt = (string) ($charge['paid_at'] ?? ($event['created_at'] ?? date('Y-m-d')));
        $occAt = (string) (
            $charge['created_at'] ?? ($bill['created_at'] ?? $event['created_at'] ?? date('Y-m-d'))
        );
        $lowDate = new DateTime(current(explode('T', $paidAt)));
        $occurrenceDate = new DateTime(current(explode('T', $occAt)));

        return [
            'tid' => (string) ($bill['id'] ?? ''),
            'transactionId' => (string) ($gwFields['transaction_id'] ?? ''),
            'paymentMethodCode' => (string) ($charge['payment_method']['code'] ?? ''),
            'statusCode' => (string) ($charge['status'] ?? ($bill['status'] ?? '')),
            'paidAt' => $paidAt,
            'occurrenceAt' => $occAt,
            'lowDate' => $lowDate,
            'occurrenceDate' => $occurrenceDate,
        ];
    }

    public function insureCustomer(Customer $customer): int
    {
        $code = (string) $customer->id;
        $response = $this->requestJson(
            'GET',
            'customers?query=' . urlencode('code:' . $code),
            [],
            true
        );
        if (isset($response['customers'][0]['id']) && is_numeric($response['customers'][0]['id'])) {
            $id = (int) $response['customers'][0]['id'];
            $payload = $this->buildCustomerPayload($customer);
            $this->requestJson('PUT', 'customers/' . $id, $payload, true);
            return $id;
        }
        $payload = $this->buildCustomerPayload($customer);
        $result = $this->requestJson('POST', 'customers', $payload, true);
        $id = (int) ($result['customer']['id'] ?? 0);
        if ($id <= 0) {
            throw new DomainException('Customer ID not found in Vindi response');
        }
        return $id;
    }

    protected function buildCustomerPayload(Customer $customer): array
    {
        $document = $customer->document ? preg_replace('/\D/', '', $customer->document) : null;
        $phoneNumber = $customer->phone ? preg_replace('/\D/', '', $customer->phone) : null;
        $payload = [
            'name' => $customer->name,
            'email' => $customer->email,
            'code' => (string) $customer->id,
            'status' => 'active',
            'registry_code' => $document,
        ];

        if ($customer->address) {
            $payload['address'] = [
                'street' => $customer->address->street,
                'number' => $customer->address->number,
                'additional_details' => $customer->address->complement ?? '',
                'zipcode' => preg_replace('/\D/', '', $customer->address->zipCode),
                'neighborhood' => $customer->address->neighborhood,
                'city' => $customer->address->city,
                'state' => strtoupper($customer->address->state),
                'country' => 'BR',
            ];
        }

        if ($phoneNumber) {
            $payload['phones'] = [
                [
                    'phone_type' => 'mobile',
                    'number' => $phoneNumber,
                ]
            ];
        }

        return array_filter($payload, static fn($value) => $value !== null && $value !== '');
    }

    public function createBill(array $payload): array
    {
        return (array) ($this->requestJson('POST', 'bills', $payload, true)['bill'] ?? []);
    }

    public function postPaymentProfiles(array $payload): array
    {
        return $this->requestJson('POST', 'payment_profiles', $payload, true);
    }

    public function postPublicPaymentProfiles(array $payload): array
    {
        return $this->requestJson('POST', 'public/payment_profiles', $payload, false);
    }

    public function getPaymentProfilesByCustomer(int $customerId): array
    {
        $response = $this->requestJson(
            'GET',
            'payment_profiles?query=' . urlencode('customer_id:' . $customerId),
            [],
            true
        );
        return (array) ($response['payment_profiles'] ?? []);
    }

    public function findExistingPaymentProfile(
        int $customerId,
        string $cardNumber,
        string $expiration,
        string $brand
    ): ?array {
        $profiles = $this->getPaymentProfilesByCustomer($customerId);
        $digits = preg_replace('/\D/', '', $cardNumber);
        $lastFour = $digits ? substr($digits, -4) : '';
        foreach ($profiles as $profile) {
            if ($this->matchesProfile((array) $profile, $lastFour, $expiration, $brand)) {
                return (array) $profile;
            }
        }
        return null;
    }

    protected function matchesProfile(
        array $profile,
        string $lastFour,
        string $expiration,
        string $brand
    ): bool {
        $profileLastFour = (string) (
            $profile['card_last_four']
            ?? $profile['card_number_last_four']
            ?? $profile['last_four_digits']
            ?? ''
        );
        if ($lastFour && $profileLastFour && $lastFour !== $profileLastFour) {
            return false;
        }
        $profileExpiration = (string) ($profile['card_expiration'] ?? '');
        if ($profileExpiration && $profileExpiration !== $expiration) {
            return false;
        }
        $profileBrand = (string) ($profile['payment_company_code'] ?? ($profile['brand'] ?? ''));
        if ($profileBrand && strtolower($profileBrand) !== strtolower($brand)) {
            return false;
        }
        return true;
    }

    public function mapVindiStatus(string $status): PaymentStatus
    {
        $normalized = strtolower($status);
        return match ($normalized) {
            'paid' => PaymentStatus::APPROVED,
            'pending', 'open' => PaymentStatus::PENDING,
            'canceled', 'cancelled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING
        };
    }

    public function checkPaymentStatus(string $transactionId): PaymentStatus
    {
        try {
            $response = $this->requestJson('GET', 'bills/' . $transactionId, [], true);
            return $this->mapVindiStatus((string) ($response['bill']['status']));
        } catch (Exception $e) {
            return PaymentStatus::FAILED;
        }
    }
}
