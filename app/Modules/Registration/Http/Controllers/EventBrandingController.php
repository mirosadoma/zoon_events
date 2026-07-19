<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class EventBrandingController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function show(string $eventId)
    {
        $tenantId = $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        return $this->success($branding ? [
            'theme_config' => $branding->theme_config,
        ] : ['theme_config' => null]);
    }

    public function update(Request $request, string $eventId)
    {
        $tenantId = $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $validated = $request->validate([
            'theme_config' => ['required', 'array'],
            'theme_config.primary_color' => ['nullable', 'string', 'max:7'],
            'theme_config.accent_color' => ['nullable', 'string', 'max:7'],
            'theme_config.background_color' => ['nullable', 'string', 'max:7'],
            'theme_config.font_family' => ['nullable', 'string', 'max:50'],
            'theme_config.logo_path' => ['nullable', 'string', 'max:500'],
            'theme_config.header_image_path' => ['nullable', 'string', 'max:500'],
        ]);

        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        if ($branding) {
            $branding->update(['theme_config' => $validated['theme_config']]);
        } else {
            $branding = EventBranding::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'brand_reference' => 'default',
                'domain_reference' => config('app.url'),
                'content_en' => [],
                'content_ar' => [],
                'sender_name_en' => 'Event',
                'sender_name_ar' => 'الفعالية',
                'status' => 'active',
                'theme_config' => $validated['theme_config'],
            ]);
        }

        return $this->success(['theme_config' => $branding->theme_config]);
    }
}
