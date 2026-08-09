<?php

namespace App\Modules\Ai\Application\Commands;

use App\Modules\Ai\Infrastructure\Persistence\Models\AssistantConversation;
use Illuminate\Console\Command;

final class PurgeAssistantTranscripts extends Command
{
    protected $signature = 'zonetec:ai:purge-transcripts {--batch=500 : Number of conversations to purge per batch}';

    protected $description = 'Purge expired assistant conversation transcripts';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $total = 0;

        do {
            $deleted = AssistantConversation::query()
                ->where('purge_after', '<=', now())
                ->limit($batchSize)
                ->delete();

            $total += $deleted;

            if ($deleted > 0) {
                $this->info("Purged {$deleted} conversations...");
            }
        } while ($deleted >= $batchSize);

        $this->info("Total purged: {$total} conversations");

        return self::SUCCESS;
    }
}
