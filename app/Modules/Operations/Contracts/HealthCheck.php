<?php

namespace App\Modules\Operations\Contracts;

use App\Modules\Operations\Application\Health\HealthCheckResult;

interface HealthCheck
{
    public function category(): string;

    public function run(): HealthCheckResult;
}
