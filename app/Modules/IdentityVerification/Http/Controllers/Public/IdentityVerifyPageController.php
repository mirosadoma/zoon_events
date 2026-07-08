<?php

namespace App\Modules\IdentityVerification\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\IdentityVerification\Application\Support\ConsentDisclosures;
use App\Modules\IdentityVerification\Application\Support\PublicOrderIdentityContext;
use App\Modules\IdentityVerification\Application\Support\RequirementResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IdentityVerifyPageController extends Controller
{
    public function __construct(
        private readonly PublicOrderIdentityContext $context,
        private readonly RequirementResolver $requirements,
    ) {}

    public function show(Request $request, string $eventSlug, string $orderToken): Response
    {
        $resolved = $this->context->resolveBySlugAndToken($request, $eventSlug, $orderToken);
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $noticeVersion = (string) config('identity-verification.consent_notice_version', 'identity-v1');
        $ticketTypeId = $resolved['attendee']->ticket_type_id !== null
            ? (string) $resolved['attendee']->ticket_type_id
            : null;

        return Inertia::render('public/identity/Verify', [
            'locale' => $locale,
            'event' => [
                'id' => (string) $resolved['event']->id,
                'slug' => (string) $resolved['event']->slug,
                'name' => [
                    'en' => $resolved['event']->name_en,
                    'ar' => $resolved['event']->name_ar,
                ],
            ],
            'attendeeId' => (string) $resolved['attendee']->id,
            'accessToken' => $orderToken,
            'noticeVersion' => $noticeVersion,
            'residencyMode' => (string) config('identity-verification.residency', 'on_premise'),
            'disclosures' => ConsentDisclosures::forNotice($noticeVersion),
            'faceFallbackEnabled' => $this->requirements->faceFallbackEnabled(
                (string) $resolved['event']->tenant_id,
                (string) $resolved['event']->id,
                $ticketTypeId,
            ),
        ]);
    }
}
