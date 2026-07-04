<?php

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;

final readonly class ConfigurePaymentAccount
{
    public function __construct(private AuditWriter $audit) {}

    public function execute(
        string $tenantId,
        string $adapterKey,
        string $secretReference,
        string $accountReference,
        string $routeToken,
        string $mode,
        string $currency,
        string $reason,
    ): PaymentAccount {
        return DB::transaction(function () use ($tenantId, $adapterKey, $secretReference, $accountReference, $routeToken, $mode, $currency, $reason): PaymentAccount {
            PaymentAccount::query()
                ->where('tenant_id', $tenantId)
                ->where('currency', $currency)
                ->where('mode', $mode)
                ->where('status', 'active')
                ->update(['status' => 'disabled']);
            $account = PaymentAccount::query()->create([
                'tenant_id' => $tenantId,
                'adapter_key' => $adapterKey,
                'secret_reference' => $secretReference,
                'account_reference' => $accountReference,
                'webhook_route_token_hash' => hash('sha256', $routeToken),
                'mode' => $mode,
                'currency' => $currency,
                'status' => 'active',
            ]);
            $this->audit->write(
                'tenant',
                $tenantId,
                'payment_account.configured',
                'succeeded',
                targetType: 'payment_account',
                targetId: $account->id,
                metadata: ['adapter' => $adapterKey, 'mode' => $mode, 'currency' => $currency, 'reason' => $reason],
            );

            return $account;
        });
    }
}
