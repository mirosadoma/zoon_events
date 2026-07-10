<?php

namespace App\Modules\Tenancy\Infrastructure\Persistence\Scopes;

use App\Exceptions\FoundationException;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContextStore::class)->currentOrNull();

        if ($context === null) {
            throw FoundationException::forbidden('tenant_context_required', 'A trusted tenant context is required.');
        }

        $builder->where($model->getTable().'.tenant_id', $context->tenant->id);
    }
}
