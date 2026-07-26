<?php

namespace App\Modules\Events\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Actions\DeactivateRegistrationInvite;
use App\Modules\Events\Application\Actions\SendPrivateEventInvites;
use App\Modules\Events\Application\Exports\InviteEmailTemplateExport;
use App\Modules\Events\Application\Support\InviteEmailFileParser;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventInviteController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
        private readonly SendPrivateEventInvites $sendInvites,
        private readonly InviteEmailFileParser $emailParser,
        private readonly InviteEmailTemplateExport $templateExport,
    ) {}

    public function send(Request $request, string $event_id)
    {
        $event = $this->event($event_id);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'name' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'in:en,ar'],
        ]);

        $result = $this->sendInvites->execute(
            $event,
            [['email' => $validated['email'], 'name' => $validated['name'] ?? null]],
            $validated['locale'] ?? app()->getLocale(),
        );

        return $this->success($result);
    }

    public function sendBulk(Request $request, string $event_id)
    {
        $event = $this->event($event_id);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'locale' => ['nullable', 'in:en,ar'],
        ]);

        $emails = $this->emailParser->parse($validated['file']);
        if ($emails === []) {
            throw FoundationException::validation(
                'no_emails_found',
                'No valid email addresses were found in the uploaded file.',
            );
        }

        $result = $this->sendInvites->execute(
            $event,
            $emails,
            $validated['locale'] ?? app()->getLocale(),
        );

        return $this->success([
            ...$result,
            'parsed' => count($emails),
        ]);
    }

    public function template(string $event_id): StreamedResponse
    {
        $this->event($event_id);

        return $this->templateExport->download();
    }

    public function destroy(string $event_id, string $invite_id, DeactivateRegistrationInvite $action)
    {
        $this->event($event_id);
        $invite = $action->execute($this->contextStore->current(), $event_id, $invite_id);

        return $this->success([
            'id' => (string) $invite->id,
            'is_active' => (bool) $invite->is_active,
        ]);
    }

    private function event(string $eventId): Event
    {
        $context = $this->contextStore->current();

        return Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);
    }
}
