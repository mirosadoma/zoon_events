<?php

namespace App\Modules\WalletPasses\Contracts;

use App\Modules\WalletPasses\Domain\Results\WalletAdapterResult;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassGenerationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassRevocationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassUpdateRequest;

interface WalletAdapter
{
    public function generate(WalletPassGenerationRequest $request): WalletAdapterResult;

    public function update(WalletPassUpdateRequest $request): WalletAdapterResult;

    public function revoke(WalletPassRevocationRequest $request): WalletAdapterResult;
}
