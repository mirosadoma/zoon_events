<?php

namespace Database\Seeders;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Seeder;

final class BuilderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', DemoAccounts::TENANT_SLUG)->first();
        if (! $tenant) {
            return;
        }

        $event = Event::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'zonetec-summit-2026')
            ->first();

        if (! $event) {
            return;
        }

        $this->seedBadgeTemplate($tenant, $event);
        $this->seedRegistrationForm($tenant, $event);
        $this->seedBranding($tenant, $event);
    }

    private function seedBadgeTemplate(Tenant $tenant, Event $event): void
    {
        BadgeTemplate::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id, 'name' => 'Conference Badge'],
            [
                'paper_size' => 'A6',
                'printer_type' => 'thermal',
                'status' => 'active',
                'orientation' => 'portrait',
                'background_color' => '#ffffff',
                'canvas_width' => 298,
                'canvas_height' => 420,
                'layout' => [
                    [
                        'id' => 'f_name',
                        'field' => 'attendee_name',
                        'x' => 40,
                        'y' => 60,
                        'width' => 220,
                        'height' => 36,
                        'fontSize' => 22,
                        'fontFamily' => 'Inter',
                        'fontWeight' => 'bold',
                        'color' => '#1e293b',
                        'textAlign' => 'center',
                        'borderRadius' => 0,
                        'rotation' => 0,
                    ],
                    [
                        'id' => 'f_company',
                        'field' => 'company',
                        'x' => 40,
                        'y' => 100,
                        'width' => 220,
                        'height' => 24,
                        'fontSize' => 14,
                        'fontFamily' => 'Inter',
                        'fontWeight' => 'normal',
                        'color' => '#64748b',
                        'textAlign' => 'center',
                        'borderRadius' => 0,
                        'rotation' => 0,
                    ],
                    [
                        'id' => 'f_title',
                        'field' => 'job_title',
                        'x' => 40,
                        'y' => 128,
                        'width' => 220,
                        'height' => 20,
                        'fontSize' => 12,
                        'fontFamily' => 'Inter',
                        'fontWeight' => 'normal',
                        'color' => '#94a3b8',
                        'textAlign' => 'center',
                        'borderRadius' => 0,
                        'rotation' => 0,
                    ],
                    [
                        'id' => 'f_qr',
                        'field' => 'qr',
                        'x' => 109,
                        'y' => 180,
                        'width' => 80,
                        'height' => 80,
                        'fontSize' => 12,
                        'fontFamily' => 'Inter',
                        'fontWeight' => 'normal',
                        'color' => '#000000',
                        'textAlign' => 'center',
                        'borderRadius' => 0,
                        'rotation' => 0,
                    ],
                    [
                        'id' => 'f_tier',
                        'field' => 'tier',
                        'x' => 89,
                        'y' => 280,
                        'width' => 120,
                        'height' => 28,
                        'fontSize' => 14,
                        'fontFamily' => 'Inter',
                        'fontWeight' => 'bold',
                        'color' => '#ffffff',
                        'textAlign' => 'center',
                        'backgroundColor' => '#3b82f6',
                        'borderRadius' => 14,
                        'rotation' => 0,
                    ],
                    [
                        'id' => 'f_logo',
                        'field' => 'organizer_logo_ref',
                        'x' => 109,
                        'y' => 340,
                        'width' => 80,
                        'height' => 40,
                        'fontSize' => 10,
                        'fontFamily' => 'Inter',
                        'fontWeight' => 'normal',
                        'color' => '#000000',
                        'textAlign' => 'center',
                        'borderRadius' => 4,
                        'rotation' => 0,
                    ],
                ],
            ],
        );
    }

    private function seedRegistrationForm(Tenant $tenant, Event $event): void
    {
        $form = RegistrationForm::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id],
            ['name' => 'Tech Conference Registration', 'status' => 'active', 'created_by_user_id' => null],
        );

        $fields = [
            ['key' => 'full_name', 'type' => 'text', 'label_en' => 'Full name', 'label_ar' => 'الاسم الكامل', 'required' => true, 'visibility' => 'public', 'width' => 'full'],
            ['key' => 'email', 'type' => 'email', 'label_en' => 'Email', 'label_ar' => 'البريد الإلكتروني', 'required' => true, 'visibility' => 'public', 'width' => 'full'],
            ['key' => 'phone', 'type' => 'phone', 'label_en' => 'Phone number', 'label_ar' => 'رقم الجوال', 'required' => true, 'visibility' => 'public', 'width' => 'half'],
            ['key' => 'company', 'type' => 'text', 'label_en' => 'Company', 'label_ar' => 'الشركة', 'required' => false, 'visibility' => 'public', 'width' => 'half', 'placeholder_en' => 'Your company name', 'placeholder_ar' => 'اسم الشركة'],
            ['key' => 'job_title', 'type' => 'text', 'label_en' => 'Job Title', 'label_ar' => 'المسمى الوظيفي', 'required' => false, 'visibility' => 'public', 'width' => 'half', 'placeholder_en' => 'e.g. Software Engineer', 'placeholder_ar' => 'مثال: مهندس برمجيات'],
            ['key' => 'country', 'type' => 'select', 'label_en' => 'Country', 'label_ar' => 'الدولة', 'required' => true, 'visibility' => 'public', 'width' => 'half', 'options' => [
                ['value' => 'sa', 'label_en' => 'Saudi Arabia', 'label_ar' => 'السعودية'],
                ['value' => 'ae', 'label_en' => 'UAE', 'label_ar' => 'الإمارات'],
                ['value' => 'eg', 'label_en' => 'Egypt', 'label_ar' => 'مصر'],
                ['value' => 'other', 'label_en' => 'Other', 'label_ar' => 'أخرى'],
            ]],
            ['key' => 'dietary', 'type' => 'radio', 'label_en' => 'Dietary Requirements', 'label_ar' => 'متطلبات الغذاء', 'required' => false, 'visibility' => 'public', 'width' => 'full', 'options' => [
                ['value' => 'none', 'label_en' => 'None', 'label_ar' => 'لا يوجد'],
                ['value' => 'vegetarian', 'label_en' => 'Vegetarian', 'label_ar' => 'نباتي'],
                ['value' => 'halal', 'label_en' => 'Halal', 'label_ar' => 'حلال'],
                ['value' => 'vegan', 'label_en' => 'Vegan', 'label_ar' => 'نباتي صرف'],
            ]],
            ['key' => 'interests', 'type' => 'multi_select', 'label_en' => 'Topics of Interest', 'label_ar' => 'المواضيع المهمة', 'required' => false, 'visibility' => 'public', 'width' => 'full', 'options' => [
                ['value' => 'ai', 'label_en' => 'AI & Machine Learning', 'label_ar' => 'الذكاء الاصطناعي'],
                ['value' => 'cloud', 'label_en' => 'Cloud Computing', 'label_ar' => 'الحوسبة السحابية'],
                ['value' => 'security', 'label_en' => 'Cybersecurity', 'label_ar' => 'الأمن السيبراني'],
                ['value' => 'web', 'label_en' => 'Web Development', 'label_ar' => 'تطوير الويب'],
            ]],
        ];

        $existingVersion = RegistrationFormVersion::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_id', $event->id)
            ->where('registration_form_id', $form->id)
            ->first();

        if (! $existingVersion) {
            $userId = \App\Models\User::query()->first()?->id;

            $version = RegistrationFormVersion::query()->create([
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'registration_form_id' => $form->id,
                'version' => 1,
                'status' => 'published',
                'fields' => $fields,
                'schema_hash' => md5(json_encode($fields)),
                'privacy_notice_version' => 'v1',
                'terms_version' => 'v1',
                'published_by_user_id' => $userId,
                'published_at' => now(),
            ]);

            $event->forceFill([
                'active_form_version_id' => $version->id,
                'status' => 'registration_open',
            ])->save();
        }
    }

    private function seedBranding(Tenant $tenant, Event $event): void
    {
        EventBranding::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id],
            [
                'brand_reference' => 'tech-conf-brand',
                'domain_reference' => config('app.url'),
                'content_en' => ['tagline' => 'Technology Conference 2026'],
                'content_ar' => ['tagline' => 'المؤتمر التقني 2026'],
                'sender_name_en' => 'Tech Conference',
                'sender_name_ar' => 'المؤتمر التقني',
                'status' => 'active',
                'theme_config' => [
                    'primary_color' => '#2563eb',
                    'accent_color' => '#7c3aed',
                    'background_color' => '#f8fafc',
                    'font_family' => 'Inter',
                ],
            ],
        );
    }
}
