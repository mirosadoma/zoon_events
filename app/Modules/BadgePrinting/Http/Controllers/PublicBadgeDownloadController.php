<?php

namespace App\Modules\BadgePrinting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Application\Actions\BuildBadgePrintDocumentAction;
use App\Modules\BadgePrinting\Application\Actions\RenderBadgePngAction;
use App\Modules\BadgePrinting\Application\Support\BadgePngToPdf;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Application\Support\ShareablePublicEventResolver;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class PublicBadgeDownloadController extends Controller
{
    public function __construct(
        private readonly ShareablePublicEventResolver $shareableEvents,
        private readonly BuildBadgePrintDocumentAction $printDocuments,
        private readonly RenderBadgePngAction $badgePng,
        private readonly BadgePngToPdf $pngToPdf,
    ) {}

    public function download(
        Request $request,
        string $locale,
        string $eventSlug,
        string $publicReference,
        string $format,
    ): SymfonyResponse {
        $format = strtolower($format);
        abort_unless(in_array($format, ['png', 'pdf', 'image'], true), 404);
        if ($format === 'image') {
            $format = 'png';
        }

        $event = $this->shareableEvents->findBySlug($eventSlug);
        $accessToken = (string) $request->query('access_token', '');
        abort_unless($accessToken !== '', 404);

        $order = Order::query()
            ->where('event_id', $event->id)
            ->where('public_reference', $publicReference)
            ->where('status', 'paid')
            ->firstOrFail();

        abort_unless(hash_equals((string) $order->access_token_hash, hash('sha256', $accessToken)), 404);

        $attendee = Attendee::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('order_id', $order->id)
            ->firstOrFail();

        $credential = Credential::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('event_id', $order->event_id)
            ->where('attendee_id', $attendee->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $template = BadgeTemplate::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if ($template === null) {
            $template = BadgeTemplate::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->orderByDesc('id')
                ->firstOrFail();
        }

        $document = $this->printDocuments->build(
            (string) $event->tenant_id,
            (string) $event->id,
            (string) $attendee->id,
            (string) $credential->id,
            $template,
            [],
            false,
        );

        $fields = $document['fields'];
        $qrPayload = is_string($fields['qr'] ?? null) ? $fields['qr'] : null;
        $png = $this->badgePng->execute($template, $fields, $qrPayload);
        abort_unless(is_string($png) && $png !== '', 503);

        $filename = 'badge-'.$publicReference;

        if ($format === 'pdf') {
            $pdf = $this->pngToPdf->convert($png);
            abort_unless(is_string($pdf) && $pdf !== '', 503);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ]);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"{$filename}.png\"",
        ]);
    }
}
