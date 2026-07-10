<?php

namespace App\Modules\Registration\Providers;

use App\Modules\Registration\Application\Submission\EncryptedSubmissionCreator;
use App\Modules\Registration\Contracts\SubmissionCreator;
use App\Modules\Registration\Contracts\SubmissionPersonalDataAnonymizer;
use App\Modules\Registration\Infrastructure\Persistence\DatabaseSubmissionPersonalDataAnonymizer;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Support\ServiceProvider;

final class RegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PersonalDataCipher::class, fn () => new PersonalDataCipher(
            (string) config('credentials.personal_data_current_key_id'),
            (array) config('credentials.personal_data_key_ring'),
        ));
        $this->app->singleton(BlindIndex::class, fn () => new BlindIndex(
            (string) config('credentials.blind_index_current_key_id'),
            (array) config('credentials.blind_index_key_ring'),
        ));
        $this->app->bind(SubmissionCreator::class, EncryptedSubmissionCreator::class);
        $this->app->bind(SubmissionPersonalDataAnonymizer::class, DatabaseSubmissionPersonalDataAnonymizer::class);
    }
}
