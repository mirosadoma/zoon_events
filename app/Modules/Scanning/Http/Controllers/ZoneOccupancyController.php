<?php

namespace App\Modules\Scanning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scanning\Application\Queries\GetEventZoneOccupancyQuery;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ZoneOccupancyController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly GetEventZoneOccupancyQuery $query,
    ) {}

    public function summary(Request $request, string $eventId): JsonResponse
    {
        $context = $this->contexts->current();

        return $this->success($this->query->summary($context->tenant->id, $eventId));
    }

    public function analytics(Request $request, string $eventId): JsonResponse
    {
        $context = $this->contexts->current();

        return $this->success($this->query->analytics($context->tenant->id, $eventId));
    }
}
