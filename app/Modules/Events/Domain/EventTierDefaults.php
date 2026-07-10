<?php

namespace App\Modules\Events\Domain;

final class EventTierDefaults
{
    /** @return array{registration_visibility:string,approval_required:bool,credential_validity_hours:int,enabled_capabilities:list<string>} */
    public function for(EventTier $tier): array
    {
        return match ($tier) {
            EventTier::Corporate => $this->defaults('private', true, 24),
            EventTier::Public => $this->defaults('public', false, 24),
            EventTier::Vip => $this->defaults('private', true, 48),
            EventTier::Vvip => $this->defaults('private', true, 48),
        };
    }

    /** @return array{registration_visibility:string,approval_required:bool,credential_validity_hours:int,enabled_capabilities:list<string>} */
    private function defaults(string $visibility, bool $approval, int $validity): array
    {
        return [
            'registration_visibility' => $visibility,
            'approval_required' => $approval,
            'credential_validity_hours' => $validity,
            'enabled_capabilities' => ['registration', 'ticketing', 'credential'],
        ];
    }
}
