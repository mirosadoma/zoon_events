<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Application\Health\HealthService;
use App\Modules\Shared\Http\Responses\RespondsWithApi;

class PlatformHealthController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly HealthService $health,
    ) {}

    public function __invoke()
    {
        return $this->success($this->health->readiness()->toArray());
    }
}
