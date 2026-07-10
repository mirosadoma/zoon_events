<?php

namespace App\Modules\Registration\Infrastructure\Persistence;

use App\Modules\Registration\Contracts\SubmissionPersonalDataAnonymizer;
use Illuminate\Support\Facades\DB;

final class DatabaseSubmissionPersonalDataAnonymizer implements SubmissionPersonalDataAnonymizer
{
    public function anonymize(string $tenantId, string $submissionId): void
    {
        DB::table('registration_submissions')->where('tenant_id', $tenantId)->where('id', $submissionId)->update([
            'answers_ciphertext' => 'anonymized',
            'encryption_key_id' => 'anonymized',
        ]);
    }
}
