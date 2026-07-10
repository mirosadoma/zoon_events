<?php

namespace App\Modules\Credentials\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Credentials\Application\Actions\ReissueCredential;
use App\Modules\Credentials\Application\Actions\RevokeCredential;
use App\Modules\Credentials\Application\Validation\CredentialValidator;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class CredentialLifecycleController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly AuditWriter $audit,
    ) {}

    public function revoke(Request $request, string $eventId, string $credentialId, RevokeCredential $action)
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $credential = $action->execute($this->contexts->current(), $eventId, $credentialId, $reason);

        return $this->success($this->map($credential));
    }

    public function reissue(Request $request, string $eventId, string $credentialId, ReissueCredential $action)
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $issued = $action->execute($this->contexts->current(), $eventId, $credentialId, $reason);
        $credential = Credential::query()->findOrFail($issued->id);

        return $this->success([...$this->map($credential), 'qr_payload' => $issued->token], 201);
    }

    public function validateCredential(Request $request, CredentialValidator $validator)
    {
        $token = $request->validate(['credential' => ['required', 'string', 'min:32', 'max:2048']])['credential'];
        $context = $this->contexts->current();
        try {
            $result = $validator->validate($token, $context->tenant->id);
        } catch (FoundationException $exception) {
            $this->audit->writeTenant(
                'credential.validation_denied',
                'denied',
                $context,
                $exception->problemCode,
                targetType: 'credential',
            );
            throw $exception;
        }

        return $this->success($result);
    }

    private function map(Credential $credential): array
    {
        return [
            'id' => $credential->id,
            'status' => $credential->status,
            'issued_at' => $credential->issued_at?->toIso8601String(),
            'expires_at' => $credential->expires_at?->toIso8601String(),
            'revoked_at' => $credential->revoked_at?->toIso8601String(),
        ];
    }
}
