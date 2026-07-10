<?php

namespace App\Modules\Registration\Contracts;

interface SubmissionPersonalDataAnonymizer
{
    public function anonymize(string $tenantId, string $submissionId): void;
}
