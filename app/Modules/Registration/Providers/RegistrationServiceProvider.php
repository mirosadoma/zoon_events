<?php

namespace App\Modules\Registration\Providers;

use App\Modules\Registration\Application\Submission\EncryptedSubmissionCreator;
use App\Modules\Registration\Contracts\SubmissionCreator;
use App\Modules\Registration\Contracts\SubmissionPersonalDataAnonymizer;
use App\Modules\Registration\Infrastructure\Persistence\DatabaseSubmissionPersonalDataAnonymizer;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use Illuminate\Support\ServiceProvider;

final class RegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PersonalDataCipher::class, function () {
            $enabled = (bool) config('credentials.personal_data_encryption_enabled', true);
            $keyId = (string) config('credentials.personal_data_current_key_id');
            $keyRing = (array) config('credentials.personal_data_key_ring');

            if (! $enabled) {
                // Provide a disposable local key so DI still resolves when encryption is off.
                $keyId = $keyId !== '' ? $keyId : 'local-dev';
                if (! isset($keyRing[$keyId]) || ! is_string($keyRing[$keyId])) {
                    $keyRing[$keyId] = base64_encode(str_repeat('d', SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
                }
            }

            return new PersonalDataCipher($keyId, $keyRing);
        });
        $this->app->singleton(BlindIndex::class, function () {
            $enabled = (bool) config('credentials.personal_data_encryption_enabled', true);
            $keyId = (string) config('credentials.blind_index_current_key_id');
            $keyRing = (array) config('credentials.blind_index_key_ring');

            if (! $enabled) {
                $keyId = $keyId !== '' ? $keyId : 'local-dev';
                if (! isset($keyRing[$keyId]) || ! is_string($keyRing[$keyId]) || strlen((string) $keyRing[$keyId]) < 16) {
                    $keyRing[$keyId] = 'local-dev-blind-index-key';
                }
            }

            return new BlindIndex($keyId, $keyRing);
        });
        $this->app->singleton(PersonalDataGuard::class, fn () => new PersonalDataGuard(
            $this->app->make(PersonalDataCipher::class),
            $this->app->make(BlindIndex::class),
            (bool) config('credentials.personal_data_encryption_enabled', true),
            (string) (config('credentials.personal_data_current_key_id') ?: 'local-dev'),
        ));
        $this->app->bind(SubmissionCreator::class, EncryptedSubmissionCreator::class);
        $this->app->bind(SubmissionPersonalDataAnonymizer::class, DatabaseSubmissionPersonalDataAnonymizer::class);
    }
}
