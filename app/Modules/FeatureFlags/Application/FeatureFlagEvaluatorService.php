<?php

namespace App\Modules\FeatureFlags\Application;

use App\Exceptions\FoundationException;
use App\Modules\FeatureFlags\Domain\FeatureFlagValueType;
use App\Modules\FeatureFlags\Infrastructure\Persistence\Models\FeatureFlag;
use App\Modules\FeatureFlags\Infrastructure\Persistence\Models\FeatureFlagOverride;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final class FeatureFlagEvaluatorService
{
    public function __construct(private readonly TenantContextStore $contexts) {}

    public function evaluate(string $key): array
    {
        if (in_array($key, (array) config('feature-flags.non_flaggable'), true)) {
            throw FoundationException::forbidden('security_control_not_flaggable', 'Mandatory controls cannot be represented as feature flags.');
        }

        $context = $this->contexts->current();
        $flag = FeatureFlag::query()->where('key', $key)->firstOrFail();
        $type = FeatureFlagValueType::from($flag->value_type);
        $default = $type->accepts($flag->default_value) ? $flag->default_value : $this->safeDefault($type);
        $override = FeatureFlagOverride::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('feature_flag_id', $flag->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($flag->status !== 'active' || ! $override instanceof FeatureFlagOverride || ! $type->accepts($override->value)) {
            return ['key' => $key, 'value_type' => $type->value, 'effective_value' => $default, 'source' => 'default', 'override_expires_at' => null];
        }

        return ['key' => $key, 'value_type' => $type->value, 'effective_value' => $override->value, 'source' => 'tenant_override', 'override_expires_at' => $override->expires_at?->toIso8601String()];
    }

    private function safeDefault(FeatureFlagValueType $type): bool|int|string
    {
        return match ($type) {
            FeatureFlagValueType::Boolean => false,
            FeatureFlagValueType::Integer => 0,
            FeatureFlagValueType::String => '',
        };
    }
}
