# SDK PHP Vindi

SDK de integração com Vindi (Bills, Pix, Cartão de Crédito e Boleto) seguindo padrão do pacote eRede OAuth.

## Funcionalidades

Este SDK possui as seguintes funcionalidades:

- Criação de faturas (Bills) com item de produto
- Pix: geração de cobrança, renderização de QR Code, consulta de status
- Cartão de crédito: tokenização (public API), criação de perfil e cobrança
- Boleto: emissão, extração de linha digitável/código de barras e consulta
- Mapeamento de status de pagamento e parser de webhook de liquidação

## Requisitos

- PHP >= 8.1
- Guzzle HTTP

## Instalação

### Via Composer

O pacote será publicado no Packagist. Por enquanto, utilize o repositório local ou Git.

```bash
composer require devsitarget/sdk-vindi-php
```

## Configuração

### Credenciais e Ambiente

Configure a loja com chave pública/privada da Vindi e ambiente:

```php
<?php
use VindiSdk\Environment;
use VindiSdk\Store;
use VindiSdk\Vindi;

$env = Environment::sandbox(); // ou Environment::production()
$store = new Store('PUBLIC_KEY', 'PRIVATE_KEY', $env);

// ID do produto configurado no painel Vindi
$productId = 363801;

$vindi = new Vindi($store, $productId);
```

## Uso Básico

### Pix: gerar cobrança

```php
<?php
use VindiSdk\Customer;
use VindiSdk\Address;
use VindiSdk\Pix\PixRequest;

$customer = new Customer(
  id: '123', name: 'Cliente', email: 'cliente@ex.com',
  document: '12345678900', phone: '11999999999',
  address: new Address('Rua A', '100', '01234-567', 'Centro', 'São Paulo', 'SP')
);

$req = new PixRequest(amount: 100.0, currency: 'BRL', customer: $customer, description: 'PIX Pedido 123');
$res = $vindi->createPixCharge($req);

// $res->qrCode contém SVG, $res->pixCopyPaste contém payload COPIA E COLA
```

### Cartão de Crédito

```php
<?php
use VindiSdk\CreditCard\CreditCard;
use VindiSdk\CreditCard\CreditCardRequest;

$card = new CreditCard(
  number: '4111111111111111', holderName: 'Cliente', expirationMonth: '12', expirationYear: '2028', securityCode: '123', brand: 'visa'
);

$ccReq = new CreditCardRequest(
  amount: 150.0,
  currency: 'BRL',
  customer: $customer,
  creditCard: $card,
  installments: 1,
  description: 'Pedido 456'
);

$ccRes = $vindi->createCreditCardPayment($ccReq);
```

### Boleto

```php
<?php
use DateTime;
use VindiSdk\Bank\BankRequest;

$due = new DateTime('+3 days');
$bankReq = new BankRequest(
  amount: 200.0,
  currency: 'BRL',
  customer: $customer,
  description: 'Pedido 789',
  dueDate: $due,
  number: 'BOL789'
);

$bankRes = $vindi->generateBank($bankReq);
// $bankRes->digitableLine, $bankRes->barCode, $bankRes->url
```

### Consultar Status

```php
<?php
$status = $vindi->checkPaymentStatus($res->tid);
```

### Webhook de Liquidação

```php
<?php
$parsed = Vindi::parseSettlementWebhook($payload);
// ['tid', 'transactionId', 'paymentMethodCode', 'statusCode', 'lowDate', 'occurrenceDate']
```

## Testes

1. Crie o arquivo `.env` a partir do exemplo:

```bash
cp env.example .env
```

2. Edite o arquivo `.env` com suas credenciais de sandbox:

```env
VINDI_PUBLIC_KEY=seu_public_key
VINDI_PRIVATE_KEY=seu_private_key
VINDI_ENVIRONMENT=sandbox
VINDI_PRODUCT_ID=363801
```

3. Execute os testes:

```bash
composer install
composer test
```

**Nota:** O arquivo `.env` é utilizado apenas nos testes de integração.

## Comandos Disponíveis

- `composer test` - Executa os testes
- `composer phpstan` - Análise estática
- `composer cs-check` - Verificação PSR-12
- `composer cs-fix` - Correções PSR-12

## Desenvolvimento

### Estrutura do Projeto

```
vindi-sdk/
├── src/
│   └── Vindi/
│       ├── Environment.php
│       ├── Store.php
│       ├── PaymentStatus.php
│       ├── VindiBaseClient.php
│       ├── Vindi.php
│       ├── Pix/
│       ├── CreditCard/
│       └── Bank/
├── tests/
│   └── Unit/
├── composer.json
├── phpunit.xml
├── env.example
└── README.md
```

### Padrões de Código

- PSR-4 para autoload
- Injeção de dependência (Guzzle Client)
- Testes unitários com mock de HTTP

## Licença

MIT
