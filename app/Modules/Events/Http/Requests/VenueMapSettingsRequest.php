<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VenueMapSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'overlay_opacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'remove_background' => ['nullable', 'boolean'],
            'show_base_map' => ['nullable', 'boolean'],
            'map_center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'map_center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'map_zoom' => ['nullable', 'numeric', 'between:1,22'],
            'map_heading' => ['nullable', 'numeric', 'between:0,360'],
            'map_type' => ['nullable', 'string', Rule::in(['roadmap', 'satellite', 'hybrid', 'terrain'])],
            'overlay_north' => ['nullable', 'numeric', 'between:-90,90'],
            'overlay_south' => ['nullable', 'numeric', 'between:-90,90'],
            'overlay_east' => ['nullable', 'numeric', 'between:-180,180'],
            'overlay_west' => ['nullable', 'numeric', 'between:-180,180'],
            'overlay_rotation' => ['nullable', 'numeric', 'between:0,360'],
        ];
    }
}
