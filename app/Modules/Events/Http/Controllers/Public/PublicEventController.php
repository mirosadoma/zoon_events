<?php

namespace App\Modules\Events\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Queries\GetPublicEvent;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Domain\Context\PublicEventContextStore;
use App\Modules\Registration\Application\Queries\GetPublicRegistrationForm;
use App\Modules\Shared\Http\Responses\RespondsWithApi;

final class PublicEventController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly PublicEventContextStore $contexts) {}

    public function show(GetPublicEvent $query)
    {
        $event = $query->execute($this->contexts->current());

        return $this->success([
            'id' => $event->id,
            'slug' => $event->slug,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'description' => ['en' => $event->description_en, 'ar' => $event->description_ar],
            'tier' => $event->tier,
            'timezone' => $event->timezone,
            'start_at' => EventWallClockDateTime::toIso8601($event->start_at, $event->timezone),
            'end_at' => EventWallClockDateTime::toIso8601($event->end_at, $event->timezone),
            'branding' => [
                'brand_reference' => $event->branding?->brand_reference,
                'content' => ['en' => $event->branding?->content_en, 'ar' => $event->branding?->content_ar],
            ],
        ]);
    }

    public function form(GetPublicRegistrationForm $query)
    {
        $result = $query->execute($this->contexts->current());

        return $this->success([
            'form' => [
                'id' => $result['form']->id,
                'version' => $result['form']->version,
                'fields' => collect($result['form']->fields)
                    ->filter(fn (array $field): bool => ($field['visibility'] ?? 'public') === 'public'
                        && ($field['type'] ?? '') !== 'hidden')
                    ->values()
                    ->all(),
                'privacy_notice_version' => $result['form']->privacy_notice_version,
                'terms_version' => $result['form']->terms_version,
            ],
            'ticket_types' => $result['tickets'],
        ]);
    }
}
