<?php

namespace App\Modules\Audit\Application\Sanitization;

use App\Modules\Shared\Support\Redaction\SafeMetadata;
use App\Modules\Shared\Support\Redaction\SecretRedactor;

final class AuditSanitizer
{
    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): array
    {
        return SafeMetadata::from(SecretRedactor::redact($metadata));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $allowedFields
     */
    public function changes(array $before, array $after, array $allowedFields): array
    {
        $changes = [];
        foreach ($allowedFields as $field) {
            if (($before[$field] ?? null) === ($after[$field] ?? null)) {
                continue;
            }
            $secret = preg_match('/password|token|secret|key|credential/i', $field) === 1;
            if ($secret) {
                $changes[$field] = ['changed' => true, 'redacted' => true];
            } else {
                $changes[$field] = SafeMetadata::from([
                    'change' => ['from' => $before[$field] ?? null, 'to' => $after[$field] ?? null],
                ])['change'];
            }
        }

        return array_slice($changes, 0, 50, true);
    }
}
