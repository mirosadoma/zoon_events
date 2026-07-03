<?php

namespace App\Modules\Shared\Http\Resources;

use App\Modules\Shared\Domain\Context\RequestContextStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection->values()->all();
    }

    public function with(Request $request): array
    {
        return [
            'meta' => ApiMeta::base(app(RequestContextStore::class)),
        ];
    }
}
