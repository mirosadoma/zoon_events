<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('organization_type', 24)
                ->default('organizer')
                ->after('status')
                ->index();
        });

        DB::table('tenants')->whereNull('organization_type')->update([
            'organization_type' => 'organizer',
        ]);

        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_organization_type_chk CHECK (organization_type IN ('organizer', 'venue_owner', 'hybrid'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CHECK tenants_organization_type_chk');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['organization_type']);
            $table->dropColumn('organization_type');
        });
    }
};
