<?php

namespace App\Modules\Payments\Infrastructure\Adapters\Moyasar;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentSecretLoader;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentRequest;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

final readonly class MoyasarPaymentGateway implements PaymentGateway
{
    public function __construct(private Factory $http, private PaymentSecretLoader $secrets) {}

    public function create(PaymentContext $context, PaymentRequest $request): PaymentResult
    {
        $response = $this->client($context)->post($this->url('/payments'), [
            'amount' => $request->amountMinor,
            'currency' => $request->currency,
            'description' => $request->orderReference,
            'callback_url' => $request->returnUrl,
            'metadata' => ['order_reference' => $request->orderReference],
        ]);

        return $this->map($response->json());
    }

    public function fetch(PaymentContext $context, string $providerPaymentId): PaymentResult
    {
        return $this->map($this->client($context)->get($this->url('/payments/'.rawurlencode($providerPaymentId)))->json());
    }

    public function refund(PaymentContext $context, string $providerPaymentId, int $amountMinor, string $currency): PaymentResult
    {
        return $this->map($this->client($context)->post($this->url('/payments/'.rawurlencode($providerPaymentId).'/refund'), [
            'amount' => $amountMinor,
        ])->json(), $currency);
    }

    private function client(PaymentContext $context): PendingRequest
    {
        $account = PaymentAccount::query()
            ->where('tenant_id', $context->tenantId)
            ->where('status', 'active')
            ->findOrFail($context->accountId);

        return $this->http
            ->withBasicAuth($this->secrets->load($account->secret_reference), '')
            ->withHeaders(['Idempotency-Key' => $context->idempotencyKey])
            ->acceptJson()
            ->asJson()
            ->timeout(max(1, (int) ceil($context->timeoutMs / 1000)))
            ->retry(1, 100, throw: false);
    }

    /** @param array<string,mixed> $payload */
    private function map(array $payload, ?string $fallbackCurrency = null): PaymentResult
    {
        $status = match ((string) ($payload['status'] ?? '')) {
            'paid', 'captured' => PaymentStatus::Captured,
            'authorized' => PaymentStatus::Authorized,
            'failed' => PaymentStatus::Failed,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Unknown,
        };

        return new PaymentResult(
            $status,
            isset($payload['id']) ? (string) $payload['id'] : null,
            (int) ($payload['amount'] ?? 0),
            (int) ($payload['refunded'] ?? 0),
            (string) ($payload['currency'] ?? $fallbackCurrency ?? 'SAR'),
            $status === PaymentStatus::Unknown ? 'unknown_outcome' : null,
            isset($payload['source']['transaction_url']) ? ['type' => 'redirect', 'url' => $payload['source']['transaction_url']] : null,
        );
    }

    private function url(string $path): string
    {
        return rtrim((string) config('payments.moyasar.api_url'), '/').$path;
    }
}
