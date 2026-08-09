<?php

namespace App\Modules\Ai\Application\Retrieval\Sources;

use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Contracts\KnowledgeSourceProvider;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final class RegistrationFactsSource implements KnowledgeSourceProvider
{
    public function __construct(
        private readonly KnowledgeChunker $chunker,
    ) {}

    public function sourceType(): string
    {
        return 'registration';
    }

    public function extract(int $tenantId, int $eventId): array
    {
        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->first();

        if ($event === null) {
            return [];
        }

        $chunks = [];

        $contentEn = $this->buildRegistrationContent($event, $tenantId, $eventId, 'en');
        if ($contentEn !== '') {
            $chunks = array_merge(
                $chunks,
                $this->chunker->chunk($contentEn, $this->sourceType(), 'registration', 'en', 'Registration Information'),
            );
        }

        $contentAr = $this->buildRegistrationContent($event, $tenantId, $eventId, 'ar');
        if ($contentAr !== '') {
            $chunks = array_merge(
                $chunks,
                $this->chunker->chunk($contentAr, $this->sourceType(), 'registration', 'ar', 'معلومات التسجيل'),
            );
        }

        return $chunks;
    }

    private function buildRegistrationContent(Event $event, int $tenantId, int $eventId, string $locale): string
    {
        $parts = [];

        if ($event->registration_opens_at && $event->registration_closes_at) {
            $label = $locale === 'ar' ? 'فترة التسجيل' : 'Registration period';
            $parts[] = "{$label}: {$event->registration_opens_at->format('Y-m-d')} - {$event->registration_closes_at->format('Y-m-d')}";
        }

        $ticketTypes = TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'published')
            ->get();

        if ($ticketTypes->isNotEmpty()) {
            $ticketLabel = $locale === 'ar' ? 'أنواع التذاكر المتاحة' : 'Available ticket types';
            $parts[] = "{$ticketLabel}:";

            foreach ($ticketTypes as $ticket) {
                $name = $locale === 'ar' ? ($ticket->name_ar ?? $ticket->name_en) : ($ticket->name_en ?? $ticket->name_ar);
                $price = $ticket->price_minor > 0
                    ? number_format($ticket->price_minor / 100, 2).' '.$ticket->currency
                    : ($locale === 'ar' ? 'مجاني' : 'Free');
                $parts[] = "- {$name}: {$price}";
            }
        }

        if ($event->capacity) {
            $label = $locale === 'ar' ? 'سعة الحدث' : 'Event capacity';
            $parts[] = "{$label}: {$event->capacity}";
        }

        return implode("\n", $parts);
    }
}
