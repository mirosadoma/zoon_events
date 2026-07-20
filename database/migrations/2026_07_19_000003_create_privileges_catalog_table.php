<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('privileges')) {
            Schema::create('privileges', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('key', 80);
                $table->string('label', 150);
                $table->string('label_ar', 150)->nullable();
                $table->string('effect', 16)->default('allow');
                $table->string('target_type', 50)->nullable();
                $table->string('target_id', 100)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unique(['tenant_id', 'key']);
            });
        }

        $hasLegacyColumns = Schema::hasColumn('category_template_privileges', 'key');
        $hasPrivilegeId = Schema::hasColumn('category_template_privileges', 'privilege_id');

        if ($hasLegacyColumns) {
            $rows = DB::table('category_template_privileges as ctp')
                ->join('category_templates as ct', 'ct.id', '=', 'ctp.category_template_id')
                ->orderBy('ctp.id')
                ->get([
                    'ct.tenant_id',
                    'ctp.category_template_id',
                    'ctp.id as link_id',
                    'ctp.key',
                    'ctp.label',
                    'ctp.label_ar',
                    'ctp.effect',
                    'ctp.target_type',
                    'ctp.target_id',
                ]);

            $privilegeIdsByTenantKey = [];
            $sortByTenant = [];

            foreach ($rows as $row) {
                $tenantKey = $row->tenant_id.'|'.$row->key;

                if (! isset($privilegeIdsByTenantKey[$tenantKey])) {
                    $existing = DB::table('privileges')
                        ->where('tenant_id', $row->tenant_id)
                        ->where('key', $row->key)
                        ->value('id');

                    if ($existing !== null) {
                        $privilegeIdsByTenantKey[$tenantKey] = $existing;
                    } else {
                        $sort = $sortByTenant[$row->tenant_id] ?? 0;
                        $sortByTenant[$row->tenant_id] = $sort + 1;

                        $privilegeIdsByTenantKey[$tenantKey] = DB::table('privileges')->insertGetId([
                            'tenant_id' => $row->tenant_id,
                            'key' => $row->key,
                            'label' => $row->label,
                            'label_ar' => $row->label_ar,
                            'effect' => $row->effect ?: 'allow',
                            'target_type' => $row->target_type,
                            'target_id' => $row->target_id,
                            'sort_order' => $sort,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            if (! $hasPrivilegeId) {
                Schema::table('category_template_privileges', function (Blueprint $table): void {
                    $table->unsignedBigInteger('privilege_id')->nullable()->after('category_template_id');
                });
            }

            foreach ($rows as $row) {
                $tenantKey = $row->tenant_id.'|'.$row->key;
                DB::table('category_template_privileges')
                    ->where('id', $row->link_id)
                    ->update(['privilege_id' => $privilegeIdsByTenantKey[$tenantKey]]);
            }

            Schema::table('category_template_privileges', function (Blueprint $table): void {
                $table->dropColumn(['key', 'label', 'label_ar', 'target_type', 'target_id']);
            });

            DB::statement('ALTER TABLE category_template_privileges MODIFY privilege_id BIGINT UNSIGNED NOT NULL');
        }

        $foreignKeys = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['category_template_privileges', 'privilege_id'],
        ))->pluck('CONSTRAINT_NAME');

        if ($foreignKeys->isEmpty()) {
            Schema::table('category_template_privileges', function (Blueprint $table): void {
                $table->foreign('privilege_id')->references('id')->on('privileges')->cascadeOnDelete();
            });
        }

        $indexes = collect(DB::select('SHOW INDEX FROM category_template_privileges'))
            ->pluck('Key_name');

        if (! $indexes->contains('ctp_template_privilege_unique')) {
            Schema::table('category_template_privileges', function (Blueprint $table): void {
                $table->unique(['category_template_id', 'privilege_id'], 'ctp_template_privilege_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('category_template_privileges', function (Blueprint $table): void {
            $table->dropForeign(['privilege_id']);
            $table->dropUnique('ctp_template_privilege_unique');
            $table->string('key')->nullable();
            $table->string('label')->nullable();
            $table->string('label_ar')->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
        });

        $links = DB::table('category_template_privileges as ctp')
            ->join('privileges as p', 'p.id', '=', 'ctp.privilege_id')
            ->get([
                'ctp.id',
                'p.key',
                'p.label',
                'p.label_ar',
                'p.target_type',
                'p.target_id',
            ]);

        foreach ($links as $link) {
            DB::table('category_template_privileges')->where('id', $link->id)->update([
                'key' => $link->key,
                'label' => $link->label,
                'label_ar' => $link->label_ar,
                'target_type' => $link->target_type,
                'target_id' => $link->target_id,
            ]);
        }

        Schema::table('category_template_privileges', function (Blueprint $table): void {
            $table->dropColumn('privilege_id');
        });

        Schema::dropIfExists('privileges');
    }
};
