<?php

namespace App\Modules\Registration\Contracts;

use App\Modules\Registration\Domain\SubmissionRecord;

interface SubmissionCreator
{
    /** @param array<string,mixed> $answers @param array<string,mixed> $consent */
    public function create(string $tenantId, string $eventId, string $formVersionId, string $idempotencyKey, array $answers, array $consent, string $locale): SubmissionRecord;
}
