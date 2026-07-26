<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Domain\EventZoneType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ZoneSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'venue_id' => ['required', 'integer', 'exists:event_venues,id'],
            'zones' => ['required', 'array', 'max:500'],
            'zones.*.id' => ['nullable', 'integer'],
            'zones.*.zone_name_en' => ['required', 'string', 'max:160'],
            'zones.*.zone_name_ar' => ['required', 'string', 'max:160'],
            'zones.*.type' => ['required', 'string', Rule::in(EventZoneType::values())],
            'zones.*.capacity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
