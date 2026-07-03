<?php

namespace App\Modules\Shared\Http\Resources;

use App\Modules\Shared\Domain\Context\RequestContextStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    public function with(Request $request): array
    {
        return [
            'meta' => ApiMeta::base(app(RequestContextStore::class)),
        ];
    }
}
