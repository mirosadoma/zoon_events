<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_roles', function (Blueprint $table): void {
            $table->string('name_en', 100)->nullable()->after('name');
            $table->string('name_ar', 100)->nullable()->after('name_en');
        });

        DB::table('tenant_roles')->orderBy('id')->each(function (object $role): void {
            DB::table('tenant_roles')
                ->where('id', $role->id)
                ->update([
                    'name_en' => $role->name,
                    'name_ar' => $role->name,
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_roles', function (Blueprint $table): void {
            $table->dropColumn(['name_en', 'name_ar']);
        });
    }
};
