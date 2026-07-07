<?php

namespace App\Modules\AccessControl\Contracts;

use App\Modules\AccessControl\Domain\Results\AcsHealthResult;

interface AcsAdapter
{
    public function health(): AcsHealthResult;
}
