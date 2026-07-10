<?php

namespace App\Modules\Registration\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Registration\Application\Validation\FormSchemaValidator;
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
        $hash = $this->schemas->canonicalHash($fields);

        return DB::transaction(function () use ($context, $eventId, $name, $fields, $hash, $privacy, $terms): RegistrationFormVersion {
            $form = RegistrationForm::query()->firstOrCreate(
                ['tenant_id' => $context->tenant->id, 'event_id' => $eventId, 'name' => $name],
                ['status' => 'draft', 'created_by_user_id' => $context->actor->id],
            );
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
}
