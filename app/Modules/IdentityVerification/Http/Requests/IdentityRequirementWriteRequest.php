<?php

namespace App\Modules\IdentityVerification\Http\Requests;

use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class IdentityRequirementWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'ticket_type_id' => ['sometimes', 'nullable', 'integer'],
            'level' => ['required', 'string', Rule::in(IdentityRequirementLevel::values())],
            'face_fallback_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['ticket_type_id', 'level', 'face_fallback_enabled'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
