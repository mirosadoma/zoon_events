<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('event_type', 32)->default('seminar')->after('tier');
            $table->string('registration_mode', 32)->default('free_registration')->after('event_type');
        });

        DB::statement("ALTER TABLE events ADD CONSTRAINT events_event_type_chk CHECK (event_type IN ('seminar','conference','workshop','corporate_gathering'))");
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_registration_mode_chk CHECK (registration_mode IN ('free_registration','paid_ticketing'))");
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_paid_ticketing_public_only_chk CHECK (registration_mode <> 'paid_ticketing' OR tier = 'public')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_paid_ticketing_public_only_chk');
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_registration_mode_chk');
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_event_type_chk');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['event_type', 'registration_mode']);
        });
    }
};
