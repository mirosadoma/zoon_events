<?php

namespace App\Modules\Registration\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Registration\Application\Validation\FormVersionPublisher;
use App\Modules\Registration\Domain\Events\RegistrationFormPublished;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class PublishFormVersion
{
    public function __construct(
        private FormVersionPublisher $publisher,
        private AuditWriter $audit,
        private EventScope $events,
    ) {}

    public function execute(TenantContext $context, string $eventId, RegistrationFormVersion $version, string $privacy, string $terms): RegistrationFormVersion
    {
        $prepared = $this->publisher->prepare($version->fields, $privacy, $terms);

        return DB::transaction(function () use ($context, $eventId, $version, $prepared): RegistrationFormVersion {
            RegistrationFormVersion::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->where('status', 'draft')
                ->whereKeyNot($version->id)
                ->update(['status' => 'retired']);

            RegistrationFormVersion::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->where('status', 'published')
                ->whereKeyNot($version->id)
                ->update(['status' => 'retired']);

            $version->forceFill([
                ...$prepared,
                'status' => 'published',
                'published_by_user_id' => $context->actor->id,
                'published_at' => now(),
            ])->save();
            $version->getConnection()->table('registration_forms')->where('id', $version->registration_form_id)->update(['status' => 'active', 'updated_at' => now()]);
            $this->events->setActiveFormVersion($context->tenant->id, $eventId, $version->id);
            $this->audit->writeTenant('registration_form.published', 'succeeded', $context, targetType: 'registration_form_version', targetId: $version->id);
            event(new RegistrationFormPublished($context->tenant->id, $eventId, $version->id, $context->actor->id));

            return $version->refresh();
        });
    }
}
