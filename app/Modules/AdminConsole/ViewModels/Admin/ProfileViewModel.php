<?php

namespace App\Modules\AdminConsole\ViewModels\Admin;

use App\Models\User;

final readonly class ProfileViewModel
{
    public function __construct(
        private User $user,
        private string $roleLabel,
        private ?array $tenant,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'profile' => [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'preferred_locale' => $this->user->preferred_locale ?? 'en',
                'role' => $this->roleLabel,
                'tenant' => $this->tenant,
                'last_login_at' => $this->user->last_authenticated_at?->toIso8601String(),
            ],
        ];
    }
}
