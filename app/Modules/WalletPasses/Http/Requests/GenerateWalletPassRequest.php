<?php

namespace App\Modules\WalletPasses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateWalletPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'access_token' => ['sometimes', 'string', 'max:256'],
        ];
    }
}
