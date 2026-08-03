<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_zones', 'scanner_code')) {
                $table->string('scanner_code', 8)->nullable()->after('capacity');
            }
        });

        // Backfill unique 8-digit codes per event for existing zones.
        $zones = DB::table('event_zones')
            ->whereNull('scanner_code')
            ->orWhere('scanner_code', '')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'event_id']);

        $usedByEvent = [];
        foreach (DB::table('event_zones')->whereNotNull('scanner_code')->get(['tenant_id', 'event_id', 'scanner_code']) as $row) {
            $key = $row->tenant_id.'|'.$row->event_id;
            $usedByEvent[$key][$row->scanner_code] = true;
        }

        foreach ($zones as $zone) {
            $key = $zone->tenant_id.'|'.$zone->event_id;
            $code = $this->uniqueCode($usedByEvent[$key] ?? []);
            $usedByEvent[$key][$code] = true;
            DB::table('event_zones')->where('id', $zone->id)->update(['scanner_code' => $code]);
        }

        Schema::table('event_zones', function (Blueprint $table): void {
            if (! $this->indexExists('event_zones', 'event_zones_scanner_code_uq')) {
                $table->unique(['tenant_id', 'event_id', 'scanner_code'], 'event_zones_scanner_code_uq');
            }
        });

        Schema::table('scan_events', function (Blueprint $table): void {
            if (! $this->indexExists('scan_events', 'scan_events_zone_occupancy_index')) {
                $table->index(
                    ['tenant_id', 'event_id', 'zone_id', 'result', 'scanned_at'],
                    'scan_events_zone_occupancy_index',
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('scan_events', function (Blueprint $table): void {
            if ($this->indexExists('scan_events', 'scan_events_zone_occupancy_index')) {
                $table->dropIndex('scan_events_zone_occupancy_index');
            }
        });

        Schema::table('event_zones', function (Blueprint $table): void {
            if ($this->indexExists('event_zones', 'event_zones_scanner_code_uq')) {
                $table->dropUnique('event_zones_scanner_code_uq');
            }
            if (Schema::hasColumn('event_zones', 'scanner_code')) {
                $table->dropColumn('scanner_code');
            }
        });
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueCode(array $used): string
    {
        for ($i = 0; $i < 50; $i++) {
            $code = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
            if (! isset($used[$code])) {
                return $code;
            }
        }

        return str_pad((string) (time() % 100_000_000), 8, '0', STR_PAD_LEFT);
    }

    private function indexExists(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $row) {
            if (($row['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
