<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final readonly class ShareablePublicEventResolver
{
    /** @var list<string> */
    private const SHAREABLE_STATUSES = ['published', 'registration_open', 'registration_closed', 'live'];

    public function __construct(private PublicEventContextResolver $eventContextResolver) {}

    public function findBySlug(string $slug): Event
    {
        $context = $this->eventContextResolver->resolve(
            mb_strtolower(request()->getHost()),
            mb_strtolower($slug),
        );

        if ($context !== null) {
            return Event::query()->findOrFail($context->eventId);
        }

        $events = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', self::SHAREABLE_STATUSES)
            ->whereNotNull('active_form_version_id')
            ->limit(2)
            ->get();

        if ($events->count() !== 1) {
            abort(404);
        }

        return $events->first();
    }
}
