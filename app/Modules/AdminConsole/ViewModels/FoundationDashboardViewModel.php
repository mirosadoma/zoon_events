<?php

namespace App\Modules\AdminConsole\ViewModels;

final readonly class FoundationDashboardViewModel
{
    /**
     * @param  list<string>  $capabilities
     * @param  array<string, mixed>  $overview
     */
    public function __construct(
        public string $scope,
        public string $title,
        public array $capabilities,
        public array $overview = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'title' => $this->title,
            'capabilities' => array_values($this->capabilities),
            'overview' => $this->overview,
        ];
    }
}
