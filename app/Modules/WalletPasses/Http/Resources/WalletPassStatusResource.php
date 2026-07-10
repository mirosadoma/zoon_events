<?php

namespace App\Modules\WalletPasses\Http\Resources;

use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletPass */
final class WalletPassStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->status->value ?? (string) $this->status;

        return [
            'pass_status' => $status,
            'save_url' => $this->pass_url,
            'sync_state' => $this->syncState($status),
        ];
    }

    private function syncState(string $status): string
    {
        if ($this->last_push_reason_code !== null
            && in_array($status, ['created', 'active'], true)) {
            return 'pending';
        }

        return 'synced';
    }
}
