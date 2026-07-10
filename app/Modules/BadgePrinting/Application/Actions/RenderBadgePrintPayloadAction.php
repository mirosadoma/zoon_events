<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Domain\ValueObjects\PrintPayload;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Support\Str;

final readonly class RenderBadgePrintPayloadAction
{
    public function __construct(private PersonalDataCipher $cipher) {}

    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        BadgeTemplate $template,
    ): PrintPayload {
        $layout = (array) $template->layout;

        $fields = [];

        foreach (array_keys($layout) as $fieldKey) {
            $fields[$fieldKey] = match ($fieldKey) {
                'attendee_name' => $this->resolveAttendeeName($tenantId, $eventId, $attendeeId),
                'qr' => $this->resolveQrPayload($tenantId, $eventId, $credentialId),
                'ticket_type' => $this->resolveTicketType($tenantId, $eventId, $credentialId),
                default => null,
            };
        }

        return new PrintPayload(
            fields: $fields,
            paperSize: $template->paper_size,
            printerType: $template->printer_type,
            idempotencyKey: Str::uuid()->toString(),
        );
    }

    private function resolveAttendeeName(string $tenantId, string $eventId, string $attendeeId): ?string
    {
        $attendee = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendeeId);

        if ($attendee === null) {
            return null;
        }

        $scope = "{$tenantId}:{$eventId}:attendee";

        try {
            $first = $this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->first_name_ciphertext],
                $scope,
            );
            $last = $this->cipher->decrypt(
                ['key_id' => $attendee->encryption_key_id, 'ciphertext' => $attendee->last_name_ciphertext],
                $scope,
            );

            return trim($first.' '.$last);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveQrPayload(string $tenantId, string $eventId, string $credentialId): ?string
    {
        $credential = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($credentialId);

        if ($credential === null || $credential->presentation_token_ciphertext === null) {
            return null;
        }

        try {
            return $this->cipher->decrypt(
                ['key_id' => $credential->key_id, 'ciphertext' => $credential->presentation_token_ciphertext],
                "{$tenantId}:{$eventId}:credential",
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTicketType(string $tenantId, string $eventId, string $credentialId): ?string
    {
        $credential = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($credentialId);

        return $credential?->ticket_type_id;
    }
}
