<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlatformDisputeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'addNote' => [
                'note' => ['required', 'string', 'min:1', 'max:4000'],
                'visibility' => ['required', 'string', 'in:participants,platform_only'],
            ],
            'resolve' => [
                'decision' => ['required', 'string', 'in:resolve,reject'],
                'resolution_code' => ['required', 'string', 'min:1', 'max:80'],
                'resolution_summary' => ['required', 'string', 'min:1', 'max:4000'],
            ],
            default => [],
        };
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
