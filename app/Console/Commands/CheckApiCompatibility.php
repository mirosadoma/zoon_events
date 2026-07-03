<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CheckApiCompatibility extends Command
{
    protected $signature = 'zonetec:api:compatibility {--baseline=docs/api/openapi-baseline.yaml}';

    protected $description = 'Detect removed Phase 0 OpenAPI operations and required fields.';

    public function handle(): int
    {
        $baselinePath = base_path((string) $this->option('baseline'));
        $currentPath = base_path('specs/001-project-foundation/contracts/openapi.yaml');
        if (! File::exists($baselinePath)) {
            $this->components->error('OpenAPI baseline is missing.');

            return self::FAILURE;
        }

        $baseline = File::get($baselinePath);
        $current = File::get($currentPath);
        preg_match_all('/operationId:\\s*([A-Za-z0-9_]+)/', $baseline, $baselineOperations);
        preg_match_all('/operationId:\\s*([A-Za-z0-9_]+)/', $current, $currentOperations);
        $removed = array_diff($baselineOperations[1], $currentOperations[1]);
        preg_match_all('/required:\\s*\\[([^\\]]+)\\]/', $baseline, $baselineRequired);
        foreach ($baselineRequired[1] as $requiredSet) {
            if (! str_contains($current, "required: [{$requiredSet}]")) {
                $removed[] = "required:[{$requiredSet}]";
            }
        }

        foreach ($removed as $item) {
            $this->components->error("Breaking OpenAPI removal: {$item}");
        }

        return $removed === [] ? self::SUCCESS : self::FAILURE;
    }
}
