<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence\Concerns;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @phpstan-require-extends Model */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenantId(): string
    {
        return (string) $this->getAttribute('tenant_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable().'.tenant_id', $tenantId);
    }

    public static function queryForCurrentTenant(): Builder
    {
        $context = app(TenantContextStore::class)->current();

        return static::query()->forTenant($context->tenant->id);
    }
}
