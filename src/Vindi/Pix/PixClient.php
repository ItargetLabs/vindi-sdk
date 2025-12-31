<?php

declare(strict_types=1);

namespace VindiSdk\Pix;

use chillerlan\QRCode\QRCode;
use DateTime;
use DomainException;
use Exception;
use VindiSdk\VindiBaseClient;

class PixClient extends VindiBaseClient
{
    public function __construct(\VindiSdk\Store $store, private int $productId)
    {
        parent::__construct($store);
    }

    public function generatePixCharge(PixRequest $request): PixResponse
    {
        try {
            $customerId = $this->insureCustomer($request->customer);
            $bill = $this->createBill([
                'customer_id' => $customerId,
                'payment_method_code' => 'pix',
                'bill_items' => [
                    [
                        'product_id' => $this->productId,
                        'description' => $request->description ?? 'PIX',
                        'amount' => (float) $request->amount
                    ],
                ],
            ]);
            if (!isset($bill['status'])) {
                throw new DomainException('Resposta inválida da API Vindi para criação do PIX (status)');
            }
            $status = $this->mapVindiStatus((string) ($bill['status']));
            $transaction = $bill['charges'][0]['last_transaction'];
            if (($transaction['status'] ?? '') === 'rejected') {
                throw new DomainException((string) ($transaction['gateway_message'] ?? ''));
            }
            $pixText = $transaction['gateway_response_fields']['qrcode_original_path'];
            $expires = 60;
            if (isset($transaction['gateway_response_fields']['max_days_to_keep_waiting_payment'])) {
                $now = new DateTime('now');
                $expiresAtStr = (string) $transaction['gateway_response_fields']['max_days_to_keep_waiting_payment'];
                $expiresAt = new DateTime($expiresAtStr);
                $diff = max(60, $expiresAt->getTimestamp() - $now->getTimestamp());
                $expires = (int) ($diff / 60);
            }
            $transactionId = $transaction['gateway_response_fields']['transaction_id'];
            return new PixResponse(
                tid: (string) $bill['id'],
                status: $status,
                amount: $request->amount,
                currency: $request->currency,
                pixId: (string) $transactionId,
                qrCode: (new QRCode())->render($pixText),
                qrCodeText: $pixText,
                pixCopyPaste: $pixText,
                expiresInMinutes: $expires,
                authorizationCode: null,
                gatewayResponse: $bill
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function generatePixQRCode(string $pixCode): string
    {
        return (new QRCode())->render($pixCode);
    }

    public function checkPixStatus(string $pixId): PixStatusResponse
    {
        try {
            $data = $this->getBill($pixId);
            if (empty($data)) {
                throw new DomainException('Resposta inválida da API Vindi');
            }
            if (!isset($data['charges'][0])) {
                throw new DomainException('Resposta inválida da API Vindi para consulta do charges PIX');
            }
            $charge = $data['charges'][0];
            if (!isset($charge['status'])) {
                throw new DomainException('Resposta inválida da API Vindi para consulta de status PIX');
            }
            $status = $this->mapVindiStatus((string) $charge['status']);
            if (!isset($charge['amount'])) {
                throw new DomainException('Resposta inválida da API Vindi para consulta de valor PIX');
            }
            $amount = (float) $charge['amount'];
            if (!isset($charge['created_at'])) {
                throw new DomainException('Resposta inválida da API Vindi para consulta de created_at PIX');
            }
            $occurrenceDate = isset($charge['created_at']) ? new DateTime((string) $charge['created_at']) : null;
            $lowDate = isset($charge['paid_at']) ? new DateTime((string) $charge['paid_at']) : null;
            $pixText = $charge['last_transaction']['gateway_response_fields']['qrcode_original_path'] ?? '';
            return new PixStatusResponse(
                status: $status,
                tid: (string) $data['id'],
                nsu: null,
                amount: $amount,
                authorizationCode: null,
                payerSolicitation: null,
                location: null,
                occurrenceDate: $occurrenceDate,
                lowDate: $lowDate,
                pixCopyPaste: $pixText
            );
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getPixPayload(string $pixId): string
    {
        $status = $this->checkPixStatus($pixId);
        return $status->pixCopyPaste ?? '';
    }
}
