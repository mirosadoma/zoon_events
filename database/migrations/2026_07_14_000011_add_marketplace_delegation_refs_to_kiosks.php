<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kiosks')) {
            return;
        }

        Schema::table('kiosks', function (Blueprint $table): void {
            if (! Schema::hasColumn('kiosks', 'delegation_public_id')) {
                $table->char('delegation_public_id', 26)->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('kiosks', 'venue_asset_public_id')) {
                $table->char('venue_asset_public_id', 26)->nullable()->after('delegation_public_id');
            }
            if (! Schema::hasColumn('kiosks', 'organizer_tenant_id')) {
                $table->unsignedBigInteger('organizer_tenant_id')->nullable()->after('venue_asset_public_id');
            }
            if (! Schema::hasColumn('kiosks', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('organizer_tenant_id');
            }

            if (! Schema::hasIndex('kiosks', 'kiosks_delegation_index')) {
                $table->index(
                    ['tenant_id', 'delegation_public_id'],
                    'kiosks_delegation_index',
                );
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('kiosks') && Schema::hasColumn('kiosks', 'delegation_public_id')) {
            Schema::table('kiosks', function (Blueprint $table): void {
                $table->dropIndex('kiosks_delegation_index');
                $table->dropColumn(['delegation_public_id', 'venue_asset_public_id', 'organizer_tenant_id', 'event_id']);
            });
        }
    }
};
