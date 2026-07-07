<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_records', function (Blueprint $table): void {
            $table->dropForeign(['actor_id']);
        });

        DB::statement('ALTER TABLE idempotency_records MODIFY actor_id VARCHAR(64) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE idempotency_records MODIFY actor_id CHAR(26) NOT NULL');

        Schema::table('idempotency_records', function (Blueprint $table): void {
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
