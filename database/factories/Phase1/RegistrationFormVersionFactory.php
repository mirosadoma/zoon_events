<?php

namespace Database\Factories\Phase1;

use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

final class RegistrationFormVersionFactory extends Factory
{
    protected $model = RegistrationFormVersion::class;

    public function definition(): array
    {
        $fields = [['key' => 'email', 'type' => 'email', 'label_en' => 'Email', 'label_ar' => 'البريد', 'required' => true]];

        return [
            'version' => 1,
            'status' => 'draft',
            'fields' => $fields,
            'schema_hash' => hash('sha256', json_encode($fields)),
            'privacy_notice_version' => 'privacy-v1',
            'terms_version' => 'terms-v1',
        ];
    }
}
