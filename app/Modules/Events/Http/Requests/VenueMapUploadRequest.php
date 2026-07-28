<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VenueMapUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'max:10240'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'overlay_north' => ['nullable', 'numeric', 'between:-90,90'],
            'overlay_south' => ['nullable', 'numeric', 'between:-90,90'],
            'overlay_east' => ['nullable', 'numeric', 'between:-180,180'],
            'overlay_west' => ['nullable', 'numeric', 'between:-180,180'],
            'map_center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'map_center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'map_zoom' => ['nullable', 'numeric', 'between:1,22'],
            'map_heading' => ['nullable', 'numeric', 'between:0,360'],
            'map_type' => ['nullable', 'string', 'in:roadmap,satellite,hybrid,terrain'],
        ];
    }
}
