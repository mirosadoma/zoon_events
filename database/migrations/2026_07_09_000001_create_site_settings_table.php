<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('app_name_en')->default('Zonetec');
            $table->string('app_name_ar')->default('زونتك');
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->text('about_en')->nullable();
            $table->text('about_ar')->nullable();
            $table->boolean('maintenance_enabled')->default(false);
            $table->text('maintenance_message_en')->nullable();
            $table->text('maintenance_message_ar')->nullable();
            $table->timestamps();
        });

        DB::table('site_settings')->insert([
            'app_name_en' => 'Zonetec',
            'app_name_ar' => 'زونتك',
            'support_email' => config('mail.from.address', 'hello@zonetec.com'),
            'support_phone' => '+20 100 000 0000',
            'about_en' => 'Zonetec is an end-to-end event operations platform for registration, identity, credentials, on-site check-in, and access control.',
            'about_ar' => 'زونتك منصة متكاملة لإدارة الفعاليات تشمل التسجيل والتحقق من الهوية وبيانات الدخول وتسجيل الحضور والتحكم في الوصول.',
            'maintenance_enabled' => false,
            'maintenance_message_en' => 'The platform is under maintenance. Please check back soon.',
            'maintenance_message_ar' => 'المنصة قيد الصيانة حالياً. يرجى المحاولة لاحقاً.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
