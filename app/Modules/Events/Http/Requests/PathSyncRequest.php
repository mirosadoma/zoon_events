<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Domain\EventCoordinateSpace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PathSyncRequest extends FormRequest
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
            'paths' => ['present', 'array', 'max:500'],
            'paths.*.id' => ['nullable', 'integer'],
            'paths.*.name_en' => ['nullable', 'string', 'max:160'],
            'paths.*.name_ar' => ['nullable', 'string', 'max:160'],
            'paths.*.coordinate_space' => ['nullable', 'string', Rule::in(EventCoordinateSpace::values())],
            'paths.*.polyline_coordinates' => ['required', 'array', 'min:2'],
            'paths.*.polyline_coordinates.*.x' => ['nullable', 'numeric', 'between:0,1'],
            'paths.*.polyline_coordinates.*.y' => ['nullable', 'numeric', 'between:0,1'],
            'paths.*.polyline_coordinates.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'paths.*.polyline_coordinates.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'paths.*.from_zone_id' => ['nullable', 'integer', 'exists:event_zones,id'],
            'paths.*.to_zone_id' => ['nullable', 'integer', 'exists:event_zones,id'],
            'paths.*.stroke_color' => ['nullable', 'string', 'max:32'],
            'paths.*.stroke_width' => ['nullable', 'integer', 'between:1,20'],
            'paths.*.opacity' => ['nullable', 'integer', 'between:0,100'],
            'paths.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
