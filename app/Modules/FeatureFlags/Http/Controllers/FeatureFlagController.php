<?php

namespace App\Modules\FeatureFlags\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\FeatureFlags\Application\FeatureFlagEvaluatorService;
use App\Modules\FeatureFlags\Infrastructure\Persistence\Models\FeatureFlag;
use App\Modules\FeatureFlags\Infrastructure\Persistence\Models\FeatureFlagOverride;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeatureFlagController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly TenantContextStore $contextStore,
        private readonly FeatureFlagEvaluatorService $evaluator,
    ) {}

    public function platformIndex()
    {
        return $this->success(FeatureFlag::query()->latest()->limit(100)->get()->map(fn (FeatureFlag $flag): array => $this->mapDefinition($flag))->all());
    }

    public function platformStore(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120', 'unique:feature_flags,key'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:500'],
            'owner' => ['required', 'string', 'max:120'],
            'value_type' => ['required', 'in:boolean,integer,string'],
            'default_value' => ['required'],
        ]);
        $this->assertFlaggable($validated['key']);
        $this->assertValueMatchesType($validated['default_value'], $validated['value_type']);

        /** @var User $actor */
        $actor = $request->user();
        $flag = DB::transaction(function () use ($validated, $actor): FeatureFlag {
            $flag = FeatureFlag::query()->create(array_merge($validated, [
                'status' => 'active',
                'security_class' => 'optional_capability',
                'created_by_user_id' => $actor->id,
            ]));
            $this->audit->writePlatform('feature_flag.created', 'succeeded', $actor, targetType: 'feature_flag', targetId: $flag->id);

            return $flag;
        });

        return $this->success($this->mapDefinition($flag), 201);
    }

    public function platformUpdate(Request $request, string $flagKey)
    {
        $flag = FeatureFlag::query()->where('key', $flagKey)->firstOrFail();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'string', 'max:500'],
            'owner' => ['sometimes', 'string', 'max:120'],
            'default_value' => ['sometimes'],
            'status' => ['sometimes', 'in:draft,active,disabled,retired'],
        ]);
        if ($flag->status === 'retired') {
            abort(409, 'Retired feature flag keys are immutable and cannot be reused.');
        }
        if (array_key_exists('default_value', $validated)) {
            $this->assertValueMatchesType($validated['default_value'], $flag->value_type);
        }

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($flag, $validated, $actor): void {
            $flag->fill($validated)->save();
            $this->audit->writePlatform('feature_flag.changed', 'succeeded', $actor, targetType: 'feature_flag', targetId: $flag->id);
        });

        return $this->success($this->mapDefinition($flag->refresh()));
    }

    public function tenantIndex()
    {
        $effective = FeatureFlag::query()
            ->whereNotIn('key', (array) config('feature-flags.non_flaggable'))
            ->orderBy('key')
            ->limit(100)
            ->pluck('key')
            ->map(fn (string $key): array => $this->evaluator->evaluate($key));

        return $this->success($effective->values()->all());
    }

    public function tenantShow(string $flagKey)
    {
        $context = $this->contextStore->current();
        $flag = FeatureFlag::query()->where('key', $flagKey)->firstOrFail();
        $this->assertFlaggable($flagKey);
        abort_unless($flag->status === 'active', 409, 'Only active feature flags can be overridden.');
        $override = FeatureFlagOverride::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('feature_flag_id', $flag->id)
            ->first();
        if ($override instanceof FeatureFlagOverride && ! $this->isEffective($override)) {
            $override = null;
        }

        return $this->success([
            'key' => $flag->key,
            'value_type' => $flag->value_type,
            'effective_value' => $override?->value ?? $flag->default_value,
            'source' => $override ? 'tenant_override' : 'default',
            'override_expires_at' => $override?->expires_at?->toIso8601String(),
        ]);
    }

    public function tenantSet(Request $request, string $flagKey)
    {
        $context = $this->contextStore->current();
        $flag = FeatureFlag::query()->where('key', $flagKey)->firstOrFail();
        $validated = $request->validate([
            'value' => ['required'],
            'reason' => ['required', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $this->assertValueMatchesType($validated['value'], $flag->value_type);

        $override = DB::transaction(function () use ($context, $flag, $validated): FeatureFlagOverride {
            $override = FeatureFlagOverride::query()->updateOrCreate(
                ['tenant_id' => $context->tenant->id, 'feature_flag_id' => $flag->id],
                [
                    'value' => $validated['value'],
                    'status' => 'active',
                    'reason' => $validated['reason'],
                    'created_by_user_id' => $context->actor->id,
                    'expires_at' => $validated['expires_at'] ?? null,
                ],
            );
            $this->audit->writeTenant('feature_flag.override_set', 'succeeded', $context, targetType: 'feature_flag_override', targetId: $override->id);

            return $override;
        });

        return $this->success([
            'key' => $flag->key,
            'value_type' => $flag->value_type,
            'effective_value' => $override->value,
            'source' => 'tenant_override',
            'override_expires_at' => $override->expires_at?->toIso8601String(),
        ]);
    }

    public function tenantDelete(string $flagKey)
    {
        $context = $this->contextStore->current();
        $flag = FeatureFlag::query()->where('key', $flagKey)->firstOrFail();
        DB::transaction(function () use ($context, $flag): void {
            FeatureFlagOverride::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('feature_flag_id', $flag->id)
                ->delete();
            $this->audit->writeTenant('feature_flag.override_removed', 'succeeded', $context, targetType: 'feature_flag', targetId: $flag->id);
        });

        return $this->empty();
    }

    private function mapDefinition(FeatureFlag $flag): array
    {
        return [
            'key' => $flag->key,
            'name' => $flag->name,
            'description' => $flag->description,
            'owner' => $flag->owner,
            'value_type' => $flag->value_type,
            'default_value' => $flag->default_value,
            'status' => $flag->status,
            'security_class' => $flag->security_class,
            'created_at' => $flag->created_at?->toIso8601String(),
        ];
    }

    private function isEffective(FeatureFlagOverride $override): bool
    {
        return $override->status === 'active'
            && ($override->expires_at === null || $override->expires_at->isFuture());
    }

    private function assertValueMatchesType(mixed $value, string $type): void
    {
        $valid = match ($type) {
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'string' => is_string($value) && mb_strlen($value) <= 500,
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'value' => ['The value does not match the feature flag value_type.'],
            ]);
        }
    }

    private function assertFlaggable(string $key): void
    {
        if (in_array($key, (array) config('feature-flags.non_flaggable'), true)) {
            throw ValidationException::withMessages([
                'key' => ['Mandatory security controls cannot be represented as feature flags.'],
            ]);
        }
    }
}
