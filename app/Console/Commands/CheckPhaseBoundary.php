<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CheckPhaseBoundary extends Command
{
    protected $signature = 'zonetec:phase-boundary:check';

    protected $description = 'Reject container files and excluded Phase 0 product implementation.';

    public function handle(): int
    {
        $failures = [];
        foreach (File::glob(base_path('Dockerfile*')) as $path) {
            $failures[] = $path;
        }
        foreach (['docker-compose.yml', 'compose.yml', 'docker-compose.yaml', 'compose.yaml'] as $name) {
            if (File::exists(base_path($name))) {
                $failures[] = base_path($name);
            }
        }

        $roots = [app_path(), resource_path('js'), base_path('routes'), database_path('migrations')];
        $forbidden = '/\\\\(Payments?|Tickets?|Credentials?|Wallet|Kiosks?|Scanners?|ACS|Marketplace|Registration)(\\\\|\\.|$)/i';
        foreach ($roots as $root) {
            foreach (File::allFiles($root) as $file) {
                if (preg_match($forbidden, $file->getPathname())) {
                    $failures[] = $file->getPathname();
                }
            }
        }

        foreach ($failures as $failure) {
            $this->components->error("Phase boundary violation: {$failure}");
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
