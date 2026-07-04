<?php

namespace Database\Factories\Phase1;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

final class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'slug' => 'synthetic-'.$this->faker->unique()->slug(2),
            'name_en' => 'Synthetic Event',
            'name_ar' => 'فعالية تجريبية',
            'tier' => 'public',
            'status' => 'draft',
            'timezone' => 'Africa/Cairo',
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHours(4),
            'registration_opens_at' => now(),
            'registration_closes_at' => now()->addMonth()->subHour(),
            'capacity' => 100,
        ];
    }
}
