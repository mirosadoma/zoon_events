<?php

namespace App\Modules\BadgePrinting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BadgeTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('badge.template.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'layout' => ['required', 'array'],
            'paper_size' => ['required', 'string', 'max:40'],
            'printer_type' => ['required', 'string', 'max:40'],
            'orientation' => ['nullable', 'string', 'in:portrait,landscape'],
            'background_color' => ['nullable', 'string', 'max:7'],
            'background_gradient' => ['nullable', 'array'],
            'background_gradient.type' => ['required_with:background_gradient', 'string', 'in:linear'],
            'background_gradient.angle' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'background_gradient.stops' => ['required_with:background_gradient', 'array', 'min:2', 'max:5'],
            'background_gradient.stops.*.color' => ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'background_gradient.stops.*.position' => ['required', 'numeric', 'min:0', 'max:100'],
            'canvas_width' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'canvas_height' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ];
    }
}
