<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'code')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->string('code', 8)->nullable()->unique()->after('slug');
            });
        }

        DB::statement('ALTER TABLE events DROP CONSTRAINT events_tier_chk');

        DB::table('events')->whereIn('tier', ['corporate', 'vip', 'vvip'])->update(['tier' => 'private']);

        $existing = DB::table('events')
            ->where(function ($query): void {
                $query->whereNull('code')->orWhere('code', '');
            })
            ->orderBy('id')
            ->pluck('id');
        $used = DB::table('events')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->pluck('code')
            ->flip()
            ->all();

        foreach ($existing as $eventId) {
            do {
                $code = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
            } while (isset($used[$code]));

            $used[$code] = true;
            DB::table('events')->where('id', $eventId)->update(['code' => $code]);
        }

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_tier_chk CHECK (tier IN ('public','private'))");
        DB::statement('ALTER TABLE events MODIFY code VARCHAR(8) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_tier_chk');
        DB::table('events')->where('tier', 'private')->update(['tier' => 'corporate']);
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_tier_chk CHECK (tier IN ('corporate','public','vip','vvip'))");

        if (Schema::hasColumn('events', 'code')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }
};
