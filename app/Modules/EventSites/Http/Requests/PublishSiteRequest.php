<?php

namespace App\Modules\EventSites\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublishSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [];
    }
}
