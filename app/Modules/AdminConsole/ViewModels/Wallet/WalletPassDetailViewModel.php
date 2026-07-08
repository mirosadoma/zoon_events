<?php

namespace App\Modules\AdminConsole\ViewModels\Wallet;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Support\Collection;

final readonly class WalletPassDetailViewModel
{
    /**
     * @param  Collection<int, WalletPass>  $passes
     * @return array{event: array<string, mixed>, walletPasses: list<array<string, mixed>>}
     */
    public function index(Event $event, Collection $passes): array
    {
        return [
            'event' => $this->eventRow($event),
            'walletPasses' => $passes->map(fn (WalletPass $pass): array => $this->passRow($pass))->values()->all(),
        ];
    }

    /**
     * @return array{event: array<string, mixed>, walletPass: array<string, mixed>}
     */
    public function detail(Event $event, WalletPass $pass): array
    {
        return [
            'event' => $this->eventRow($event),
            'walletPass' => [
                ...$this->passRow($pass),
                'credential_id' => $pass->credential_id,
                'last_pushed_at' => $pass->last_pushed_at?->toIso8601String(),
                'last_push_reason_code' => $pass->last_push_reason_code,
                'pass_content_updated_at' => $pass->pass_content_updated_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
        ];
    }

    /** @return array<string, mixed> */
    private function passRow(WalletPass $pass): array
    {
        return [
            'id' => $pass->id,
            'provider' => $pass->provider,
            'serial' => $pass->pass_serial_number,
            'attendee_id' => $pass->attendee_id,
            'credential_id' => $pass->credential_id,
            'status' => $pass->status instanceof \BackedEnum ? $pass->status->value : (string) $pass->status,
            'pass_url' => $pass->pass_url,
            'last_pushed_at' => $pass->last_pushed_at?->toIso8601String(),
        ];
    }
}
