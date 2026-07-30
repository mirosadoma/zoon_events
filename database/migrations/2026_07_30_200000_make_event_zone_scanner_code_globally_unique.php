<?php

use App\Modules\Events\Application\Support\ZoneScannerCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deduplicate scanner codes across events so login can resolve by code alone.
        $seen = [];
        $zones = DB::table('event_zones')
            ->whereNotNull('scanner_code')
            ->where('scanner_code', '!=', '')
            ->orderBy('id')
            ->get(['id', 'scanner_code']);

        foreach ($zones as $zone) {
            $code = (string) $zone->scanner_code;
            if (! isset($seen[$code])) {
                $seen[$code] = true;
                continue;
            }

            $replacement = null;
            for ($i = 0; $i < 40; $i++) {
                $candidate = ZoneScannerCode::generate();
                if (! isset($seen[$candidate])) {
                    $replacement = $candidate;
                    $seen[$candidate] = true;
                    break;
                }
            }

            if ($replacement === null) {
                throw new RuntimeException('Unable to regenerate duplicate scanner codes.');
            }

            DB::table('event_zones')->where('id', $zone->id)->update(['scanner_code' => $replacement]);
        }

        Schema::table('event_zones', function (Blueprint $table): void {
            if ($this->indexExists('event_zones', 'event_zones_scanner_code_uq')) {
                $table->dropUnique('event_zones_scanner_code_uq');
            }
        });

        Schema::table('event_zones', function (Blueprint $table): void {
            if (! $this->indexExists('event_zones', 'event_zones_scanner_code_global_uq')) {
                $table->unique('scanner_code', 'event_zones_scanner_code_global_uq');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            if ($this->indexExists('event_zones', 'event_zones_scanner_code_global_uq')) {
                $table->dropUnique('event_zones_scanner_code_global_uq');
            }
        });

        Schema::table('event_zones', function (Blueprint $table): void {
            if (! $this->indexExists('event_zones', 'event_zones_scanner_code_uq')) {
                $table->unique(['tenant_id', 'event_id', 'scanner_code'], 'event_zones_scanner_code_uq');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'select 1 as ok from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index],
        );

        return $row !== null;
    }
};
