<?php

namespace App\Modules\WalletPasses\Infrastructure\Secrets;

use App\Modules\WalletPasses\Contracts\WalletSecretLoader;
use Illuminate\Support\Env;
use InvalidArgumentException;

final class EnvironmentWalletSecretLoader implements WalletSecretLoader
{
    public function loadCertificate(string $reference): string
    {
        if ($reference === '') {
            $this->guardProductionFallback('Apple certificate');

            return SyntheticWalletMaterial::appleCertificate();
        }

        return $this->loadString($reference);
    }

    public function loadPrivateKey(string $reference): string
    {
        if ($reference === '') {
            $this->guardProductionFallback('Apple private key');

            return SyntheticWalletMaterial::applePrivateKey();
        }

        return $this->loadString($reference);
    }

    public function loadGoogleServiceAccount(string $reference): array
    {
        if ($reference === '') {
            $this->guardProductionFallback('Google service account');

            return SyntheticWalletMaterial::googleServiceAccount();
        }

        $decoded = json_decode($this->loadString($reference), true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Google service account reference is invalid.');
        }

        return $decoded;
    }

    private function guardProductionFallback(string $material): void
    {
        if (app()->environment('production', 'staging')) {
            throw new InvalidArgumentException(
                "Synthetic wallet material cannot be used in production ({$material} reference is empty)."
            );
        }
    }

    private function loadString(string $reference): string
    {
        $value = Env::get($reference);
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Wallet secret reference is unavailable.');
        }

        return $value;
    }
}
