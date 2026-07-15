<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acs_zones')) {
            return;
        }

        Schema::table('acs_zones', function (Blueprint $table): void {
            if (! Schema::hasColumn('acs_zones', 'delegation_public_id')) {
                $table->char('delegation_public_id', 26)->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('acs_zones', 'venue_asset_public_id')) {
                $table->char('venue_asset_public_id', 26)->nullable()->after('delegation_public_id');
            }
            if (! Schema::hasColumn('acs_zones', 'organizer_tenant_id')) {
                $table->unsignedBigInteger('organizer_tenant_id')->nullable()->after('venue_asset_public_id');
            }
            if (! Schema::hasColumn('acs_zones', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('organizer_tenant_id');
            }

            if (! Schema::hasIndex('acs_zones', 'acs_zones_delegation_index')) {
                $table->index(
                    ['tenant_id', 'delegation_public_id'],
                    'acs_zones_delegation_index',
                );
            }
        });

        if (! Schema::hasTable('acs_lanes')) {
            return;
        }

        Schema::table('acs_lanes', function (Blueprint $table): void {
            if (! Schema::hasColumn('acs_lanes', 'delegation_public_id')) {
                $table->char('delegation_public_id', 26)->nullable()->after('tenant_id');
            }
            if (! Schema::hasColumn('acs_lanes', 'venue_asset_public_id')) {
                $table->char('venue_asset_public_id', 26)->nullable()->after('delegation_public_id');
            }
            if (! Schema::hasColumn('acs_lanes', 'organizer_tenant_id')) {
                $table->unsignedBigInteger('organizer_tenant_id')->nullable()->after('venue_asset_public_id');
            }
            if (! Schema::hasColumn('acs_lanes', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('organizer_tenant_id');
            }

            if (! Schema::hasIndex('acs_lanes', 'acs_lanes_delegation_index')) {
                $table->index(
                    ['tenant_id', 'delegation_public_id'],
                    'acs_lanes_delegation_index',
                );
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('acs_lanes') && Schema::hasColumn('acs_lanes', 'delegation_public_id')) {
            Schema::table('acs_lanes', function (Blueprint $table): void {
                $table->dropIndex('acs_lanes_delegation_index');
                $table->dropColumn(['delegation_public_id', 'venue_asset_public_id', 'organizer_tenant_id', 'event_id']);
            });
        }

        if (Schema::hasTable('acs_zones') && Schema::hasColumn('acs_zones', 'delegation_public_id')) {
            Schema::table('acs_zones', function (Blueprint $table): void {
                $table->dropIndex('acs_zones_delegation_index');
                $table->dropColumn(['delegation_public_id', 'venue_asset_public_id', 'organizer_tenant_id', 'event_id']);
            });
        }
    }
};
