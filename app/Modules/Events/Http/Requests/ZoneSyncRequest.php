<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Domain\EventCoordinateSpace;
use App\Modules\Events\Domain\EventZoneFloorType;
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
            'zones.*.description_en' => ['nullable', 'string', 'max:5000'],
            'zones.*.description_ar' => ['nullable', 'string', 'max:5000'],
            'zones.*.type' => ['required', 'string', Rule::in(EventZoneType::values())],
            'zones.*.floor_type' => ['nullable', 'string', Rule::in(EventZoneFloorType::values())],
            'zones.*.floor_number' => ['nullable', 'integer', 'min:0', 'max:500', 'required_if:zones.*.floor_type,floor'],
            'zones.*.capacity' => ['nullable', 'integer', 'min:0'],
            'zones.*.shape_type' => ['nullable', 'string', Rule::in(EventZoneShapeType::values())],
            'zones.*.coordinate_space' => ['nullable', 'string', Rule::in(EventCoordinateSpace::values())],
            'zones.*.polygon_coordinates' => ['nullable', 'array'],
            'zones.*.polygon_coordinates.*.x' => ['nullable', 'numeric', 'between:0,1'],
            'zones.*.polygon_coordinates.*.y' => ['nullable', 'numeric', 'between:0,1'],
            'zones.*.polygon_coordinates.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'zones.*.polygon_coordinates.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'zones.*.shape_radius' => ['nullable', 'numeric', 'gt:0', 'lte:50000'],
            'zones.*.shape_rotation' => ['nullable', 'numeric', 'between:-360,360'],
            'zones.*.shape_radius_y' => ['nullable', 'numeric', 'gt:0', 'lte:50000'],
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
