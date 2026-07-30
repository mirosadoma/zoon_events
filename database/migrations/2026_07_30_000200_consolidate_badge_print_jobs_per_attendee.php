<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('badge_print_jobs')
            ->select('tenant_id', 'event_id', 'attendee_id')
            ->whereNotNull('attendee_id')
            ->groupBy('tenant_id', 'event_id', 'attendee_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('badge_print_jobs')
                ->where('tenant_id', $group->tenant_id)
                ->where('event_id', $group->event_id)
                ->where('attendee_id', $group->attendee_id)
                ->orderByDesc('id')
                ->get();

            if ($rows->count() < 2) {
                continue;
            }

            $keep = $rows->first();
            $totalPrints = $rows->sum(function ($row): int {
                if ((string) $row->status !== 'printed') {
                    return 0;
                }

                $count = (int) ($row->print_count ?? 0);

                return $count > 0 ? $count : 1;
            });

            $deleteIds = $rows->skip(1)->pluck('id')->all();
            if ($deleteIds !== []) {
                DB::table('badge_print_jobs')->whereIn('id', $deleteIds)->delete();
            }

            DB::table('badge_print_jobs')->where('id', $keep->id)->update([
                'print_count' => max(1, $totalPrints),
                'is_reprint' => $totalPrints > 1,
                'printed_at' => $rows->max('printed_at') ?? $keep->printed_at,
                'updated_at' => now(),
            ]);
        }

        Schema::table('badge_print_jobs', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'event_id', 'attendee_id'], 'badge_print_jobs_tenant_event_attendee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('badge_print_jobs', function (Blueprint $table): void {
            $table->dropUnique('badge_print_jobs_tenant_event_attendee_unique');
        });
    }
};
