<?php

namespace App\Modules\Registration\Domain;

final readonly class SubmissionRecord
{
    public function __construct(
        public string $id,
        public string $encryptionKeyId,
    ) {}
}
