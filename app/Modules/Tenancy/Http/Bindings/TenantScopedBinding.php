<?php

namespace App\Modules\Tenancy\Http\Bindings;

use App\Exceptions\FoundationException;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class TenantScopedBinding
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function register(string $parameter, string $modelClass, string $column = 'id'): void
    {
        Route::bind($parameter, function (string $value) use ($modelClass, $column): Model {
            $context = app(TenantContextStore::class)->current();

            /** @var Model|null $model */
            $model = $modelClass::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where($column, $value)
                ->where('tenant_id', $context->tenant->id)
                ->first();

            if ($model === null) {
                throw FoundationException::notFound();
            }

            return $model;
        });
    }
}
