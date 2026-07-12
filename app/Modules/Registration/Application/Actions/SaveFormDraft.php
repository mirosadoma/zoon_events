<?php

namespace App\Modules\Registration\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Registration\Application\Validation\FormSchemaValidator;
use App\Modules\Registration\Domain\Fields\FormFieldChoiceOptions;
use App\Modules\Registration\Domain\Fields\FormFieldType;
use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class SaveFormDraft
{
    public function __construct(
        private FormSchemaValidator $schemas,
        private AuditWriter $audit,
    ) {}

    /** @param list<array<string,mixed>> $fields */
    public function execute(TenantContext $context, string $eventId, string $name, array $fields, string $privacy, string $terms): RegistrationFormVersion
    {
        $fields = $this->normalizeFields($fields);
        $hash = $this->schemas->canonicalHash($fields);

        return DB::transaction(function () use ($context, $eventId, $name, $fields, $hash, $privacy, $terms): RegistrationFormVersion {
            $form = RegistrationForm::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->first();

            if ($form === null) {
                $form = RegistrationForm::query()->create([
                    'tenant_id' => $context->tenant->id,
                    'event_id' => $eventId,
                    'name' => $name,
                    'status' => 'draft',
                    'created_by_user_id' => $context->actor->id,
                ]);
            } elseif ($form->name !== $name) {
                $form->forceFill(['name' => $name])->save();
            }

            $nextVersion = (int) $form->versions()->max('version') + 1;
            $version = $form->versions()->create([
                'tenant_id' => $context->tenant->id,
                'event_id' => $eventId,
                'version' => $nextVersion,
                'status' => 'draft',
                'fields' => $fields,
                'schema_hash' => $hash,
                'privacy_notice_version' => $privacy,
                'terms_version' => $terms,
            ]);
            $this->audit->writeTenant('registration_form.draft_saved', 'succeeded', $context, targetType: 'registration_form_version', targetId: $version->id);

            return $version;
        });
    }

    /** @param list<array<string,mixed>> $fields @return list<array<string,mixed>> */
    private function normalizeFields(array $fields): array
    {
        $fields = RegistrationSystemFields::enforce($fields);

        return array_map(function (array $field): array {
            $type = FormFieldType::tryFrom((string) ($field['type'] ?? ''));

            if ($type === null || ! in_array($type, [FormFieldType::Select, FormFieldType::MultiSelect, FormFieldType::Radio, FormFieldType::Checkbox], true)) {
                return $field;
            }

            $field['options'] = FormFieldChoiceOptions::normalizeForStorage(is_array($field['options'] ?? null) ? $field['options'] : []);

            return $field;
        }, $fields);
    }
}
