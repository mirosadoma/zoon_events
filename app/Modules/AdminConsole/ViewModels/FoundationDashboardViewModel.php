<?php

namespace App\Modules\AdminConsole\ViewModels;

final readonly class FoundationDashboardViewModel
{
    public function __construct(
        public string $scope,
        public string $title,
        public array $capabilities,
    ) {}

    public function toArray(): array
    {
        return ['scope' => $this->scope, 'title' => $this->title, 'capabilities' => array_values($this->capabilities)];
    }
}
