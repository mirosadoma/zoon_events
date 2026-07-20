<?php

namespace App\Modules\Ticketing\Http\Requests;

use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

final class TicketTypeWriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'regex:/^[A-Z0-9_-]{2,40}$/'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.ar' => ['required', 'string', 'max:160'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'attendee_type' => ['nullable', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'sale_starts_at' => ['required', 'date'],
            'sale_ends_at' => ['required', 'date', 'after:sale_starts_at'],
            'status' => ['sometimes', 'string', 'in:draft,active,paused,retired'],
        ];
    }

    /** @return array<string,mixed> */
    public function attributesForAction(): array
    {
        $data = $this->validated();
        $timezone = $this->eventTimezone();

        return [
            'code' => $data['code'],
            'name_en' => $data['name']['en'],
            'name_ar' => $data['name']['ar'],
            'description_en' => $data['description']['en'] ?? null,
            'description_ar' => $data['description']['ar'] ?? null,
            'attendee_type' => $data['attendee_type'] ?? 'general',
            'capacity' => $data['capacity'],
            'base_price_minor' => $data['price_minor'],
            'currency' => $data['currency'],
            'sale_starts_at' => EventWallClockDateTime::parseToAppStorage((string) $data['sale_starts_at'], $timezone)?->toDateTimeString(),
            'sale_ends_at' => EventWallClockDateTime::parseToAppStorage((string) $data['sale_ends_at'], $timezone)?->toDateTimeString(),
            'status' => $data['status'] ?? 'active',
        ];
    }

    private function eventTimezone(): string
    {
        $eventId = $this->route('event_id') ?? $this->route('eventId');
        if ($eventId === null || $eventId === '') {
            return 'UTC';
        }

        $timezone = Event::query()->whereKey($eventId)->value('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }
}
