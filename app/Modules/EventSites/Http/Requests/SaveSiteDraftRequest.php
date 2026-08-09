<?php

namespace App\Modules\EventSites\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveSiteDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
            'page_mode' => ['sometimes', 'string', 'in:single,multi'],
            'blocks' => ['required', 'array', 'max:80'],
            'blocks.*.id' => ['required', 'string', 'max:64'],
            'blocks.*.type' => ['required', 'string', 'max:32'],
            'blocks.*.visible' => ['required', 'boolean'],
            'blocks.*.page_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'blocks.*.content_en' => ['required', 'array'],
            'blocks.*.content_ar' => ['required', 'array'],
            'blocks.*.options' => ['sometimes', 'array'],
            'blocks.*.refs' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
            'settings.show_assistant' => ['sometimes', 'boolean'],
            'settings.seo' => ['sometimes', 'array'],
            'settings.page_mode' => ['sometimes', 'string', 'in:single,multi'],
            'settings.pages' => ['sometimes', 'array', 'max:20'],
            'settings.pages.*.id' => ['required_with:settings.pages', 'string', 'max:64'],
            'settings.pages.*.slug' => ['required_with:settings.pages', 'string', 'max:80'],
            'settings.pages.*.title_en' => ['sometimes', 'nullable', 'string', 'max:190'],
            'settings.pages.*.title_ar' => ['sometimes', 'nullable', 'string', 'max:190'],
            'settings.pages.*.is_home' => ['sometimes', 'boolean'],
            'settings.pages.*.background' => ['sometimes', 'array'],
            'settings.site_background' => ['sometimes', 'array'],
            'settings.public_path_prefix' => ['sometimes', 'string', 'in:e,events'],
            'settings.public_slug' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u'],
        ];
    }
}
