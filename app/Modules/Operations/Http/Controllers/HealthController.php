<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Application\Health\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly HealthService $health,
    ) {}

    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
        ]);
    }

    public function ready(): JsonResponse
    {
        $report = $this->health->readiness();

        return response()->json(
            [
                'status' => $report->status === 'ok' ? 'ok' : 'unavailable',
            ],
            $report->status === 'ok' ? 200 : 503,
        );
    }
}
