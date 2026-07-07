<?php

namespace App\Modules\BadgePrinting\Contracts;

use App\Modules\BadgePrinting\Domain\Results\PrinterHealthResult;
use App\Modules\BadgePrinting\Domain\Results\PrintResult;
use App\Modules\BadgePrinting\Domain\ValueObjects\PrintPayload;

interface PrinterAdapter
{
    public function print(PrintPayload $payload): PrintResult;

    public function health(): PrinterHealthResult;
}
