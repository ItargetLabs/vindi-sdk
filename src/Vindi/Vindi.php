<?php

declare(strict_types=1);

namespace VindiSdk;

use VindiSdk\Bank\BankClient;
use VindiSdk\Bank\BankRequest;
use VindiSdk\Bank\BankResponse;
use VindiSdk\CreditCard\CreditCardClient;
use VindiSdk\CreditCard\CreditCardRequest;
use VindiSdk\CreditCard\CreditCardResponse;
use VindiSdk\Pix\PixClient;
use VindiSdk\Pix\PixRequest;
use VindiSdk\Pix\PixResponse;

class Vindi
{
    public function __construct(private Store $store, private int $productId)
    {
    }

    public function createCreditCardPayment(CreditCardRequest $request): CreditCardResponse
    {
        $client = new CreditCardClient($this->store, $this->productId);
        return $client->processPayment($request);
    }

    public function createPixCharge(PixRequest $request): PixResponse
    {
        $client = new PixClient($this->store, $this->productId);
        return $client->generatePixCharge($request);
    }

    public function generateBank(BankRequest $request): BankResponse
    {
        $client = new BankClient($this->store, $this->productId);
        return $client->generateBank($request);
    }

    public function checkPaymentStatus(string $transactionId): PaymentStatus
    {
        $base = new VindiBaseClient($this->store);
        return $base->checkPaymentStatus($transactionId);
    }

    public static function parseSettlementWebhook(array $payload): array
    {
        return VindiBaseClient::parseSettlementWebhook($payload);
    }
}
