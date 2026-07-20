<?php

namespace App\Modules\AdminConsole\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Application\Support\EventVenuePresenter;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class VisitorPortalController extends Controller
{
    public function __construct(
        private readonly PersonalDataCipher $cipher,
        private readonly EventVenuePresenter $venues,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->visitor($request);
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        $attendees = Attendee::query()
            ->where('user_id', $user->id)
            ->whereNull('anonymized_at')
            ->orderByDesc('registered_at')
            ->get();

        $eventIds = $attendees->pluck('event_id')->unique()->filter()->all();
        $events = Event::query()->whereIn('id', $eventIds)->get()->keyBy('id');
        $orders = Order::query()
            ->whereIn('id', $attendees->pluck('order_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $rows = $attendees->map(function (Attendee $attendee) use ($events, $orders, $locale): array {
            $event = $events->get($attendee->event_id);
            $order = $orders->get($attendee->order_id);

            return [
                'attendee_id' => (string) $attendee->id,
                'event_id' => (string) $attendee->event_id,
                'event_name' => $event === null
                    ? 'Event'
                    : ($locale === 'ar' ? ($event->name_ar ?: $event->name_en) : $event->name_en),
                'event_slug' => $event?->slug,
                'starts_at' => $event?->start_at?->toIso8601String(),
                'ends_at' => $event?->end_at?->toIso8601String(),
                'registration_status' => $attendee->registration_status,
                'order_reference' => $order?->public_reference,
                'registered_at' => $attendee->registered_at?->toIso8601String(),
            ];
        })->values()->all();

        return Inertia::render('visitor/Events', [
            'locale' => $locale,
            'events' => $rows,
        ]);
    }

    public function showEvent(Request $request): Response
    {
        $user = $this->visitor($request);
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $eventId = (string) $request->route('eventId');

        $attendee = Attendee::query()
            ->where('user_id', $user->id)
            ->where('event_id', $eventId)
            ->whereNull('anonymized_at')
            ->firstOrFail();

        $event = Event::query()->findOrFail($attendee->event_id);
        $order = $attendee->order_id
            ? Order::query()->find($attendee->order_id)
            : null;

        $selectedVenueId = $attendee->event_venue_id
            ?? $order?->event_venue_id;

        $venues = $this->venues->forEvent($event);
        $selectedVenue = null;
        if ($selectedVenueId !== null) {
            $selectedVenue = collect($venues)->firstWhere('id', (string) $selectedVenueId);
            if ($selectedVenue === null) {
                $venue = EventVenue::query()
                    ->with(['city:id,country_id,name_en,name_ar', 'country:id,name_en,name_ar'])
                    ->where('event_id', $event->id)
                    ->find($selectedVenueId);
                if ($venue !== null) {
                    $selectedVenue = [
                        'id' => (string) $venue->id,
                        'name' => ['en' => $venue->name_en, 'ar' => $venue->name_ar],
                        'city' => [
                            'en' => $venue->city?->name_en ?? '',
                            'ar' => $venue->city?->name_ar ?? '',
                        ],
                        'country' => [
                            'en' => $venue->country?->name_en ?? '',
                            'ar' => $venue->country?->name_ar ?? '',
                        ],
                        'location_address' => $venue->location_address ?? '',
                        'start_at' => EventWallClockDateTime::toIso8601($venue->start_at, $event->timezone),
                        'end_at' => EventWallClockDateTime::toIso8601($venue->end_at, $event->timezone),
                    ];
                }
            }
        }

        $category = null;
        $categoryId = $order?->event_category_id;
        if ($categoryId !== null) {
            $categoryModel = EventCategory::query()
                ->where('event_id', $event->id)
                ->find($categoryId);
            if ($categoryModel !== null) {
                $category = [
                    'id' => (string) $categoryModel->id,
                    'name' => [
                        'en' => $categoryModel->name,
                        'ar' => $categoryModel->name_ar ?: $categoryModel->name,
                    ],
                    'color' => $categoryModel->color,
                ];
            }
        }

        return Inertia::render('visitor/EventDetail', [
            'locale' => $locale,
            'event' => [
                'id' => (string) $event->id,
                'slug' => $event->slug,
                'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
                'description' => [
                    'en' => $event->description_en,
                    'ar' => $event->description_ar,
                ],
                'timezone' => $event->timezone,
                'status' => $event->status,
                'location_name' => [
                    'en' => $event->location_name_en,
                    'ar' => $event->location_name_ar,
                ],
                'location_address' => [
                    'en' => $event->location_address_en,
                    'ar' => $event->location_address_ar,
                ],
                'main_image_url' => $event->main_image_path
                    ? asset('storage/'.$event->main_image_path)
                    : null,
                'venues' => $venues,
            ],
            'registration' => [
                'attendee_id' => (string) $attendee->id,
                'status' => $attendee->registration_status,
                'registered_at' => $attendee->registered_at?->toIso8601String(),
                'order_reference' => $order?->public_reference,
                'attendee_name' => $this->resolveAttendeeName($attendee),
                'category' => $category,
                'selected_venue' => $selectedVenue,
            ],
        ]);
    }

    public function profile(Request $request): Response
    {
        $user = $this->visitor($request);

        return Inertia::render('visitor/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'preferred_locale' => $user->preferred_locale ?? 'en',
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->visitor($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'preferred_locale' => ['required', 'in:en,ar'],
        ]);

        $user->forceFill([
            'name' => trim($validated['name']),
            'preferred_locale' => $validated['preferred_locale'],
        ])->save();

        return back()->with('success', true);
    }

    public function passwordForm(): Response
    {
        return Inertia::render('visitor/Password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->visitor($request);
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('success', true);
    }

    private function visitor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isVisitor(), 403);

        return $user;
    }

    private function resolveAttendeeName(Attendee $attendee): string
    {
        try {
            $scope = "{$attendee->tenant_id}:{$attendee->event_id}:attendee";
            $first = $this->cipher->decrypt([
                'key_id' => $attendee->encryption_key_id,
                'ciphertext' => $attendee->first_name_ciphertext,
            ], $scope);
            $last = $this->cipher->decrypt([
                'key_id' => $attendee->encryption_key_id,
                'ciphertext' => $attendee->last_name_ciphertext,
            ], $scope);

            return trim($first.' '.$last) ?: 'Participant';
        } catch (Throwable) {
            return 'Participant';
        }
    }
}
