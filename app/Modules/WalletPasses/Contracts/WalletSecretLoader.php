<?php

namespace App\Modules\WalletPasses\Contracts;

interface WalletSecretLoader
{
    public function loadCertificate(string $reference): string;

    public function loadPrivateKey(string $reference): string;

    /** @return array<string, mixed> */
    public function loadGoogleServiceAccount(string $reference): array;
}
