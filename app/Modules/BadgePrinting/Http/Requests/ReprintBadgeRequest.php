<?php

namespace App\Modules\BadgePrinting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReprintBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reprint_reason' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
