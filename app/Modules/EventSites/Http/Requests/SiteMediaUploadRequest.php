<?php

namespace App\Modules\EventSites\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SiteMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ];
    }
}
