<?php

namespace App\Modules\Tenancy\Application\Actions;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Tenancy\Domain\Events\TenantStatusChanged;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class ChangeTenantStatus
{
    public function __construct(
        private readonly AuditedTransaction $transaction,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(Tenant $tenant, array $changes, User $actor, string $reason): Tenant
    {
        $from = $tenant->status->value;
        $fromIsActive = (bool) $tenant->is_active;

        if (array_key_exists('is_active', $changes) && ! array_key_exists('status', $changes)) {
            $changes['status'] = $changes['is_active'] ? 'active' : 'suspended';
        }

        $to = $changes['status'] ?? $from;
        $allowed = ['active' => ['active', 'suspended', 'deactivated'], 'suspended' => ['active', 'suspended', 'deactivated'], 'deactivated' => ['deactivated']];
        if (! in_array($to, $allowed[$from], true)) {
            throw FoundationException::conflict('tenant_transition_invalid', "Tenant cannot transition from {$from} to {$to}.");
        }

        if (! array_key_exists('is_active', $changes)) {
            $changes['is_active'] = $to === 'active';
        }

        $result = $this->transaction->run(function () use ($tenant, $changes, $to): Tenant {
            $tenant->fill($changes);
            $tenant->is_active = (bool) ($changes['is_active'] ?? ($to === 'active'));
            $tenant->suspended_at = $to === 'suspended' ? now() : null;
            $tenant->deactivated_at = $to === 'deactivated' ? now() : null;
            $tenant->save();

            return $tenant;
        }, fn (Tenant $changed) => $this->audit->writePlatform('tenant.updated', 'succeeded', $actor, targetType: 'tenant', targetId: $changed->id, metadata: ['reason' => $reason], changeSummary: [
            'status' => ['from' => $from, 'to' => $to],
            'is_active' => ['from' => $fromIsActive, 'to' => (bool) $changed->is_active],
        ]));

        DB::afterCommit(fn () => event(new TenantStatusChanged($tenant->id, $actor->id, $from, $to, $reason)));

        return $result->refresh();
    }
}
