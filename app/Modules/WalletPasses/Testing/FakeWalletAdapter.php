<?php

namespace App\Modules\WalletPasses\Testing;

use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Domain\Results\WalletAdapterResult;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassGenerationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassRevocationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassUpdateRequest;

final class FakeWalletAdapter implements WalletAdapter
{
    /** @var list<array{operation:string,request:object}> */
    private array $calls = [];

    public function generate(WalletPassGenerationRequest $request): WalletAdapterResult
    {
        $this->calls[] = ['operation' => 'generate', 'request' => $request];

        if ($request->credentialStatus !== 'active') {
            return new WalletAdapterResult('failed', null, 'credential_not_active');
        }

        return new WalletAdapterResult(
            'created',
            "https://wallet.test/{$request->provider}/{$request->passSerialNumber}",
            authenticationToken: $request->provider === 'apple' ? 'apple-auth-'.$request->passSerialNumber : null,
        );
    }

    public function update(WalletPassUpdateRequest $request): WalletAdapterResult
    {
        $this->calls[] = ['operation' => 'update', 'request' => $request];

        if (str_starts_with($request->passSerialNumber, '01UNAVAILABLE')) {
            return new WalletAdapterResult('unavailable', null, 'wallet_provider_unavailable');
        }

        if (str_starts_with($request->passSerialNumber, '01UNKNOWN')) {
            return new WalletAdapterResult('failed', null, 'wallet_pass_not_found');
        }

        return new WalletAdapterResult('updated');
    }

    public function revoke(WalletPassRevocationRequest $request): WalletAdapterResult
    {
        $this->calls[] = ['operation' => 'revoke', 'request' => $request];

        if (str_starts_with($request->passSerialNumber, '01UNAVAILABLE')) {
            return new WalletAdapterResult('unavailable', null, 'wallet_provider_unavailable');
        }

        if (str_starts_with($request->passSerialNumber, '01UNKNOWN')) {
            return new WalletAdapterResult('failed', null, 'wallet_pass_not_found');
        }

        return new WalletAdapterResult('revoked');
    }

    /** @return list<array{operation:string,request:object}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
