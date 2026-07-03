<?php

namespace App\Modules\Audit\Application;

use App\Models\User;
use App\Modules\Audit\Application\Integrity\AuditIntegrityService;
use App\Modules\Audit\Application\Sanitization\AuditSanitizer;
use App\Modules\Audit\Contracts\AuditWriter as AuditWriterContract;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Shared\Contracts\Clock;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Http\Request;

class AuditWriter implements AuditWriterContract
{
    public function __construct(
        private readonly Clock $clock,
        private readonly RequestContextStore $requestContextStore,
        private readonly Request $request,
        private readonly AuditIntegrityService $integrity,
        private readonly AuditSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $changeSummary
     */
    public function write(
        string $scope,
        ?string $tenantId,
        string $action,
        string $outcome,
        ?User $actor = null,
        ?string $reasonCode = null,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
        ?array $changeSummary = null,
    ): AuditLog {
        $requestContext = $this->requestContextStore->current();
        $payload = [
            'scope' => $scope,
            'tenant_id' => $tenantId,
            'actor_type' => $actor instanceof User ? 'user' : 'anonymous',
            'actor_id' => $actor?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'channel' => 'api',
            'correlation_id' => $requestContext?->correlationId->value ?? '',
            'request_id' => $requestContext?->requestId->value,
            'source_fingerprint' => $this->fingerprint($this->request->ip()),
            'client_fingerprint' => $this->fingerprint((string) $this->request->userAgent()),
            'change_summary' => $changeSummary === null ? null : $this->sanitizer->metadata($changeSummary),
            'metadata' => $this->sanitizer->metadata($metadata),
            'occurred_at' => $this->clock->now(),
            'integrity_algorithm' => (string) config('audit.hmac_algorithm'),
            'integrity_key_id' => (string) config('audit.current_key_id'),
        ];

        $record = new AuditLog;
        $record->forceFill($payload);
        $record->integrity_hash = $this->integrity->sign($record->integrityPayload());
        $record->save();

        return $record;
    }

    public function writeTenant(string $action, string $outcome, TenantContext $context, ?string $reasonCode = null, ?string $targetType = null, ?string $targetId = null, array $metadata = [], ?array $changeSummary = null): AuditLog
    {
        return $this->write('tenant', $context->tenant->id, $action, $outcome, $context->actor, $reasonCode, $targetType, $targetId, $metadata, $changeSummary);
    }

    public function writePlatform(string $action, string $outcome, User $actor, ?string $reasonCode = null, ?string $targetType = null, ?string $targetId = null, array $metadata = [], ?array $changeSummary = null): AuditLog
    {
        return $this->write('platform', null, $action, $outcome, $actor, $reasonCode, $targetType, $targetId, $metadata, $changeSummary);
    }

    private function fingerprint(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr(hash('sha256', $value), 0, 64);
    }
}
