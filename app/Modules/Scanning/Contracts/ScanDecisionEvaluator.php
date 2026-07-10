<?php

namespace App\Modules\Scanning\Contracts;

use App\Modules\Scanning\Domain\Results\ScanDecision;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;

interface ScanDecisionEvaluator
{
    public function evaluate(ScanContext $context): ScanDecision;
}
