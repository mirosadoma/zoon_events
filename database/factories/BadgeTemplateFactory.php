<?php

namespace Database\Factories;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BadgeTemplate> */
final class BadgeTemplateFactory extends Factory
{
    protected $model = BadgeTemplate::class;

    public function definition(): array
    {
        return [
            'name'         => 'Default Badge Template',
            'layout'       => ['attendee_name' => [], 'qr' => [], 'ticket_type' => []],
            'paper_size'   => 'a6',
            'printer_type' => 'fake',
            'status'       => 'active',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
