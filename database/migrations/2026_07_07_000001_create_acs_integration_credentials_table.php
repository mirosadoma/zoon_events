<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_integration_credentials', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('name', 120);
            $table->string('secret_hash', 255);
            $table->json('capabilities');
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at', 6);
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'status']);
        });

        DB::statement("ALTER TABLE acs_integration_credentials ADD CONSTRAINT acs_integration_credentials_status_chk CHECK (status IN ('active','revoked'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_integration_credentials');
    }
};
