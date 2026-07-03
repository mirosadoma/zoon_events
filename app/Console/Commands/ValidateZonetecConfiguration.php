<?php

namespace App\Console\Commands;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;
use Illuminate\Console\Command;

final class ValidateZonetecConfiguration extends Command
{
    protected $signature = 'zonetec:config:validate {--json}';

    protected $description = 'Validate deployment configuration without exposing values.';

    public function handle(ConfigurationValidator $validator): int
    {
        $issues = $validator->validate();
        if ($this->option('json')) {
            $this->line(json_encode(['valid' => $issues === [], 'issues' => array_map(fn ($issue) => $issue->toArray(), $issues)], JSON_THROW_ON_ERROR));
        } else {
            foreach ($issues as $issue) {
                $this->components->error("[{$issue->key}] {$issue->message}");
            }
            if ($issues === []) {
                $this->components->info('Zonetec configuration is valid.');
            }
        }

        return $issues === [] ? self::SUCCESS : self::FAILURE;
    }
}
