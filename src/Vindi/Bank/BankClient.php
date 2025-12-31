<?php

declare(strict_types=1);

namespace VindiSdk\Bank;

use DateTime;
use DomainException;
use Exception;
use VindiSdk\PaymentStatus;
use VindiSdk\VindiBaseClient;

class BankClient extends VindiBaseClient
{
    public function __construct(\VindiSdk\Store $store, private int $productId)
    {
        parent::__construct($store);
    }

    public function generateBank(BankRequest $request): BankResponse
    {
        try {
            $customerId = $this->insureCustomer($request->customer);
            $bill = $this->createBill([
                'customer_id' => $customerId,
                'payment_method_code' => 'bank_slip',
                'bill_items' => [
                    [
                        'product_id' => $this->productId,
                        'description' => $request->description,
                        'amount' => (float) $request->amount,
                    ],
                ],
                'due_at' => $request->dueDate->format('Y-m-d'),
            ]);
            $status = $this->mapVindiStatus((string) ($bill['status']));
            $boletoData = $this->extractBoletoData($bill);
            $paramsUrl = [
                'number' => $request->number,
                'accountReceiveIds' => $request->metadata['accountReceiveIds'] ?? [],
                'gatewayId' => $request->metadata['gatewayId'] ?? null
            ];
            $token = base64_encode(json_encode($paramsUrl, JSON_THROW_ON_ERROR));
            $baseUrl = rtrim((string) (\function_exists('url') ? (string) \url('/') : ''), '/');
            if (!isset($bill['charges'][0])) {
                throw new DomainException('Resposta inválida da API Vindi para geração de boleto');
            }
            $transactionId = (string) $bill['id'];
            return new BankResponse(
                tid: $transactionId,
                status: $status,
                amount: $request->amount,
                currency: $request->currency,
                digitableLine: $boletoData['digitable_line'],
                barCode: $boletoData['bar_code'],
                url: $baseUrl ? $baseUrl . '/api/payments/bank/print?' . http_build_query(['token' => $token]) : '',
                hash: (string) $bill['id'],
                authorizationCode: null,
                gatewayResponse: $bill
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function isBankPaid(string $bankId): bool
    {
        try {
            $status = $this->getBankData($bankId);
            return $status->status === PaymentStatus::APPROVED;
        } catch (Exception) {
            return false;
        }
    }

    public function getBankData(string $bankId, array $searchParams = []): BankStatusResponse
    {
        try {
            $response = $this->getBill($bankId);
            if (empty($response)) {
                throw new DomainException('Resposta inválida da API Vindi para consulta de boleto');
            }
            if (!isset($response['charges'][0])) {
                throw new DomainException('Resposta inválida da API Vindi para consulta do charges do boleto');
            }
            $charge = $response['charges'][0];
            $status = $this->mapVindiStatus((string) ($charge['status'] ?? ''));
            if (!isset($charge['amount'])) {
                throw new DomainException('Resposta inválida da API Vindi para consulta de valor do boleto');
            }
            $amount = (float) ($charge['amount']);
            $boletoData = $this->extractBoletoData($response);
            $dueDate = isset($response['due_at']) ? new DateTime((string) $response['due_at']) : null;
            $issueDate = isset($response['created_at']) ? new DateTime((string) $response['created_at']) : null;
            return new BankStatusResponse(
                status: $status,
                transactionId: (string) ($response['id'] ?? $bankId),
                amount: $amount,
                digitableLine: $boletoData['digitable_line'] ?: null,
                barCode: $boletoData['bar_code'] ?: null,
                bankNumber: (string) ($response['code'] ?? null),
                dueDate: $dueDate,
                issueDate: $issueDate,
                occurrenceDate: $issueDate,
                rawResponse: $response
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getBankFile(string $bankId, array $searchParams): array
    {
        $bill = $this->getBill((string) ($searchParams['hashTransaction'] ?? $bankId));
        return [
            'bankId' => $bankId,
            'link' => $bill['charges'][0]['print_url'] ?? ''
        ];
    }

    private function extractBoletoData(array $bill): array
    {
        $digitable = (string) ($bill['digitable_line'] ?? ($bill['linha_digitavel'] ?? ''));
        $barcode = (string) ($bill['bar_code'] ?? ($bill['codigo_barras'] ?? ''));
        if ((empty($digitable) || empty($barcode)) && isset($bill['charges'][0]) && is_array($bill['charges'][0])) {
            $charge = $bill['charges'][0];
            $digitable = (string) ($charge['digitable_line'] ?? ($charge['linha_digitavel'] ?? $digitable));
            $barcode = (string) ($charge['bar_code'] ?? ($charge['codigo_barras'] ?? $barcode));
        }
        return [
            'digitable_line' => $digitable,
            'bar_code' => $barcode,
        ];
    }
}
