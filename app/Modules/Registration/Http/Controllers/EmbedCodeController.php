<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class EmbedCodeController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function __invoke(Request $request, string $eventId)
    {
        $tenantId = $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->firstOrFail();

        $locale = $request->query('locale', 'en');
        $baseUrl = config('app.url');
        $registrationUrl = "{$baseUrl}/{$locale}/events/{$event->slug}/register";

        $iframe = '<iframe src="' . $registrationUrl . '" width="100%" height="800" frameborder="0" style="border:none;max-width:680px;margin:0 auto;display:block;" allow="clipboard-write"></iframe>';

        return $this->success([
            'url' => $registrationUrl,
            'iframe' => $iframe,
            'slug' => $event->slug,
        ]);
    }
}
