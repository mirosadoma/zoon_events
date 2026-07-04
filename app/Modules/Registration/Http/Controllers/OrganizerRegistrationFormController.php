<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Registration\Application\Actions\PublishFormVersion;
use App\Modules\Registration\Application\Actions\SaveFormDraft;
use App\Modules\Registration\Http\Requests\RegistrationFormWriteRequest;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final class OrganizerRegistrationFormController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function save(RegistrationFormWriteRequest $request, string $eventId, SaveFormDraft $action)
    {
        $this->event($eventId);
        $data = $request->validated();
        $version = $action->execute(
            $this->contexts->current(),
            $eventId,
            $data['name'],
            $data['fields'],
            $data['privacy_notice_version'],
            $data['terms_version'],
        );

        return $this->success($this->map($version), 201);
    }

    public function publish(string $eventId, PublishFormVersion $action)
    {
        $this->event($eventId);
        $version = RegistrationFormVersion::query()
            ->where('tenant_id', $this->contexts->current()->tenant->id)
            ->where('event_id', $eventId)
            ->where('status', 'draft')
            ->latest('version')
            ->firstOrFail();
        $version = $action->execute(
            $this->contexts->current(),
            $eventId,
            $version,
            (string) $version->privacy_notice_version,
            (string) $version->terms_version,
        );

        return $this->success($this->map($version));
    }

    private function event(string $id): void
    {
        abort_unless($this->events->exists($this->contexts->current()->tenant->id, $id), 404);
    }

    private function map(RegistrationFormVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'status' => $version->status,
            'fields' => $version->fields,
            'schema_hash' => $version->schema_hash,
            'privacy_notice_version' => $version->privacy_notice_version,
            'terms_version' => $version->terms_version,
        ];
    }
}
