<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Domain\EventZoneShapeType;
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
            'zones.*.shape_type' => ['nullable', 'string', Rule::in(EventZoneShapeType::values())],
            'zones.*.polygon_coordinates' => ['nullable', 'array'],
            'zones.*.polygon_coordinates.*.x' => ['required_with:zones.*.polygon_coordinates', 'numeric', 'between:0,1'],
            'zones.*.polygon_coordinates.*.y' => ['required_with:zones.*.polygon_coordinates', 'numeric', 'between:0,1'],
            'zones.*.shape_radius' => ['nullable', 'numeric', 'gt:0', 'lte:1'],
            'zones.*.label' => ['nullable', 'string', 'max:160'],
            'zones.*.google_maps_url' => ['nullable', 'string', 'max:1024'],
            'zones.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'zones.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'zones.*.fill_color' => ['nullable', 'string', 'max:32'],
            'zones.*.stroke_color' => ['nullable', 'string', 'max:32'],
            'zones.*.opacity' => ['nullable', 'integer', 'between:0,100'],
            'zones.*.stroke_width' => ['nullable', 'integer', 'between:0,20'],
        ];
    }
}
