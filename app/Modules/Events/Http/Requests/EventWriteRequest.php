<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Domain\EventTier;
use App\Modules\Events\Domain\EventType;
use App\Modules\Events\Domain\RegistrationMode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EventWriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:100'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.ar' => ['required', 'string', 'max:160'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'tier' => ['nullable', Rule::in(EventTier::values())],
            'event_type' => ['nullable', Rule::in(EventType::values())],
            'organizer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'timezone' => ['required', 'string', 'max:64', Rule::exists('timezones', 'identifier')],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'location_name.en' => ['nullable', 'string', 'max:200'],
            'location_name.ar' => ['nullable', 'string', 'max:200'],
            'location_address.en' => ['nullable', 'string', 'max:500'],
            'location_address.ar' => ['nullable', 'string', 'max:500'],
            'brand_reference' => ['nullable', 'string', 'max:120'],
            'domain_reference' => ['nullable', 'string', 'max:253'],
            'theme_config' => ['nullable', 'array'],
            'theme_config.primary_color' => ['nullable', 'string', 'max:7'],
            'theme_config.accent_color' => ['nullable', 'string', 'max:7'],
            'theme_config.background_color' => ['nullable', 'string', 'max:7'],
            'theme_config.text_color' => ['nullable', 'string', 'max:7'],
            'brand_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'sponsor_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'venues' => ['required', 'array', 'min:1'],
            'venues.*.id' => ['nullable', 'integer'],
            'venues.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'venues.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'venues.*.name.en' => ['required', 'string', 'max:160'],
            'venues.*.name.ar' => ['required', 'string', 'max:160'],
            'venues.*.location_address' => ['nullable', 'string', 'max:500'],
            'venues.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'venues.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'venues.*.start_at' => ['required', 'date'],
            'venues.*.end_at' => ['required', 'date', 'after:venues.*.start_at'],
            'venues.*.registration_opens_at' => ['required', 'date'],
            'venues.*.registration_closes_at' => ['required', 'date', 'before_or_equal:venues.*.end_at'],
            'main_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $venues = $this->input('venues', []);
            if (! is_array($venues)) {
                return;
            }

            foreach ($venues as $index => $venue) {
                if (! is_array($venue)) {
                    continue;
                }

                $opensRaw = $venue['registration_opens_at'] ?? null;
                $closesRaw = $venue['registration_closes_at'] ?? null;
                if (! is_string($opensRaw) || ! is_string($closesRaw) || $opensRaw === '' || $closesRaw === '') {
                    continue;
                }

                try {
                    $timezone = (string) $this->input('timezone', config('app.timezone', 'UTC'));
                    $opens = CarbonImmutable::parse($opensRaw, $timezone)->startOfDay();
                    $closes = CarbonImmutable::parse($closesRaw, $timezone)->startOfDay();
                } catch (\Throwable) {
                    continue;
                }

                // Same calendar day is allowed (single-day registration window).
                if ($closes->lt($opens)) {
                    $validator->errors()->add(
                        "venues.{$index}.registration_closes_at",
                        __('validation.after_or_equal', [
                            'attribute' => "venues.{$index}.registration_closes_at",
                            'date' => "venues.{$index}.registration_opens_at",
                        ]),
                    );
                }
            }
        });
    }

    /** @return array<string,mixed> */
    public function attributesForAction(): array
    {
        $data = $this->validated();
        $timezone = (string) $data['timezone'];
        $venues = collect($data['venues'] ?? [])->map(function (array $venue) use ($timezone): array {
            $opens = EventWallClockDateTime::parseToAppStorage(
                isset($venue['registration_opens_at']) ? (string) $venue['registration_opens_at'] : null,
                $timezone,
            );
            $closes = EventWallClockDateTime::parseToAppStorage(
                isset($venue['registration_closes_at']) ? (string) $venue['registration_closes_at'] : null,
                $timezone,
            );
            $start = EventWallClockDateTime::parseToAppStorage(
                isset($venue['start_at']) ? (string) $venue['start_at'] : null,
                $timezone,
            );
            $end = EventWallClockDateTime::parseToAppStorage(
                isset($venue['end_at']) ? (string) $venue['end_at'] : null,
                $timezone,
            );

            return [
                'id' => $venue['id'] ?? null,
                'country_id' => $venue['country_id'] ?? null,
                'city_id' => $venue['city_id'] ?? null,
                'name_en' => $venue['name']['en'],
                'name_ar' => $venue['name']['ar'],
                'location_address' => $venue['location_address'] ?? null,
                'latitude' => $venue['latitude'] ?? null,
                'longitude' => $venue['longitude'] ?? null,
                'start_at' => $start?->toDateTimeString(),
                'end_at' => $end?->toDateTimeString(),
                'registration_opens_at' => $opens?->toDateTimeString(),
                'registration_closes_at' => $closes?->toDateTimeString(),
            ];
        })->all();

        $schedule = self::scheduleFromVenues($venues);
        $tier = $data['tier'] ?? EventTier::Public->value;

        return [
            'slug' => $data['slug'],
            'name_en' => $data['name']['en'],
            'name_ar' => $data['name']['ar'],
            'description_en' => $data['description']['en'] ?? null,
            'description_ar' => $data['description']['ar'] ?? null,
            'tier' => $tier,
            'event_type' => $data['event_type'] ?? EventType::Seminar->value,
            'registration_mode' => RegistrationMode::FreeRegistration->value,
            'organizer_user_id' => isset($data['organizer_user_id']) ? (int) $data['organizer_user_id'] : null,
            'timezone' => $timezone,
            'start_at' => $schedule['start_at'],
            'end_at' => $schedule['end_at'],
            'registration_opens_at' => $schedule['registration_opens_at'],
            'registration_closes_at' => $schedule['registration_closes_at'],
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null,
            'location_name_en' => $data['location_name']['en'] ?? null,
            'location_name_ar' => $data['location_name']['ar'] ?? null,
            'location_address_en' => $data['location_address']['en'] ?? null,
            'location_address_ar' => $data['location_address']['ar'] ?? null,
            'brand_reference' => $data['brand_reference'] ?? null,
            'domain_reference' => $data['domain_reference'] ?? null,
            'theme_config' => $data['theme_config'] ?? null,
            'venues' => $venues,
        ];
    }

    /**
     * Derive the event-level schedule from the venue rows: the event spans
     * from the earliest venue start to the latest venue end, and registration
     * from the earliest opening to the latest closing.
     *
     * @param  list<array<string,mixed>>  $venues
     * @return array{start_at:string,end_at:string,registration_opens_at:string,registration_closes_at:string}
     */
    private static function scheduleFromVenues(array $venues): array
    {
        $starts = array_map(fn (array $venue): CarbonImmutable => CarbonImmutable::parse((string) $venue['start_at'], 'UTC'), $venues);
        $ends = array_map(fn (array $venue): CarbonImmutable => CarbonImmutable::parse((string) $venue['end_at'], 'UTC'), $venues);
        $opens = array_map(fn (array $venue): CarbonImmutable => CarbonImmutable::parse((string) $venue['registration_opens_at'], 'UTC'), $venues);
        $closes = array_map(fn (array $venue): CarbonImmutable => CarbonImmutable::parse((string) $venue['registration_closes_at'], 'UTC'), $venues);

        return [
            'start_at' => min($starts)->toDateTimeString(),
            'end_at' => max($ends)->toDateTimeString(),
            'registration_opens_at' => min($opens)->toDateTimeString(),
            'registration_closes_at' => max($closes)->toDateTimeString(),
        ];
    }
}
