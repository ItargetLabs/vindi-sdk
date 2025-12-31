<?php

declare(strict_types=1);

namespace VindiSdk\CreditCard;

use Exception;
use VindiSdk\PaymentStatus;
use VindiSdk\VindiBaseClient;

class CreditCardClient extends VindiBaseClient
{
    public function __construct(\VindiSdk\Store $store, private int $productId)
    {
        parent::__construct($store);
    }

    public function processPayment(CreditCardRequest $request): CreditCardResponse
    {
        return $this->processCreditCardPayment($request);
    }

    public function processCreditCardPayment(CreditCardRequest $request): CreditCardResponse
    {
        try {
            $customerId = $this->insureCustomer($request->customer);
            $expiration = sprintf('%s/%s', $request->creditCard->expirationMonth, $request->creditCard->expirationYear);
            $brandCode = $this->mapBrandToVindiCode((string) ($request->creditCard->toArray()['brand'] ?? ''));
            $existingProfile = $this->findExistingPaymentProfile(
                $customerId,
                $request->creditCard->number,
                $expiration,
                $brandCode
            );

            $profile = $existingProfile && isset($existingProfile['id']) ? $existingProfile : null;
            if (!$profile) {
                $token = $this->tokenizeCard([
                    'holder_name' => $request->creditCard->holderName,
                    'card_expiration' => $expiration,
                    'card_number' => $request->creditCard->number,
                    'card_cvv' => $request->creditCard->securityCode,
                    'payment_method_code' => 'credit_card',
                    'payment_company_code' => $brandCode,
                ]);
                $profile = $this->createPaymentProfile([
                    'customer_id' => $customerId,
                    'gateway_token' => $token,
                    'payment_method_code' => 'credit_card',
                ]);
            }

            $bill = $this->createBill([
                'customer_id' => $customerId,
                'payment_method_code' => 'credit_card',
                'payment_profile' => [
                    'id' => $profile['id']
                ],
                'bill_items' => [
                    [
                        'product_id' => $this->productId,
                        'description' => $request->description ?? 'Pagamento',
                        'amount' => (float) $request->amount,
                    ],
                ],
            ]);
            if (!isset($bill['status'])) {
                throw new \DomainException('Falha na criação da fatura');
            }
            $status = $this->mapVindiStatus((string) $bill['status']);
            $installmentAmount = $request->installments && $request->installments > 1
                ? $request->amount / $request->installments
                : null;

            return new CreditCardResponse(
                tid: (string) $bill['id'],
                status: $status,
                amount: $request->amount,
                currency: $request->currency,
                nsu: null,
                installments: $request->installments && $request->installments > 1 ? $request->installments : null,
                installmentAmount: $installmentAmount,
                authorizationCode: null,
                gatewayResponse: $bill
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function processInstallmentPayment(CreditCardRequest $request, int $installments): CreditCardResponse
    {
        $modified = new CreditCardRequest(
            amount: $request->amount,
            currency: $request->currency,
            customer: $request->customer,
            creditCard: $request->creditCard,
            installments: $installments,
            description: $request->description,
            metadata: $request->metadata
        );
        return $this->processCreditCardPayment($modified);
    }

    public function tokenizeCard(array $cardData): string
    {
        $result = $this->postPublicPaymentProfiles($cardData);
        $token = isset($result['payment_profile']['gateway_token'])
            ? trim((string) $result['payment_profile']['gateway_token'])
            : '';
        if (empty($token)) {
            throw new Exception('Falha na tokenização do cartão');
        }
        return $token;
    }

    public function processTokenPayment(string $token, float $amount): CreditCardResponse
    {
        try {
            $profile = $this->createPaymentProfile([
                'gateway_token' => $token,
                'payment_method_code' => 'credit_card',
            ]);
            $bill = $this->createBill([
                'payment_method_code' => 'credit_card',
                'payment_profile' => ['id' => $profile['id'] ?? null],
                'bill_items' => [
                    [
                        'description' => 'Pagamento',
                        'amount' => $amount,
                    ],
                ],
            ]);
            return new CreditCardResponse(
                tid: (string) ($bill['id'] ?? uniqid()),
                status: $this->mapVindiStatus((string) ($bill['status'] ?? 'pending')),
                amount: $amount,
                currency: 'BRL',
                gatewayResponse: $bill
            );
        } catch (Exception $e) {
            return new CreditCardResponse(
                tid: 'failed_' . uniqid(),
                status: PaymentStatus::FAILED,
                amount: $amount,
                currency: 'BRL',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function mapBrandToVindiCode(string $brand): string
    {
        $normalized = strtolower($brand);
        if ($normalized === 'master') {
            return 'mastercard';
        }
        return $normalized;
    }

    private function createPaymentProfile(array $payload): array
    {
        $return = $this->postPaymentProfiles($payload);
        return $return['payment_profile'] ?? [];
    }
}
