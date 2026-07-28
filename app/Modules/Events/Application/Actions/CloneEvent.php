<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Application\Support\EventSlug;
use App\Modules\Events\Application\Support\ResolvesEventOrganizer;
use App\Modules\Events\Domain\EventZoneType;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventEmailTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\EventImage;
use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationForm;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class CloneEvent
{
    public function __construct(
        private AuditWriter $audit,
        private ResolvesEventOrganizer $organizers,
    ) {}

    public function execute(TenantContext $context, Event $source, string $nameEn, string $nameAr): Event
    {
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);

        return DB::transaction(function () use ($context, $source, $nameEn, $nameAr): Event {
            $tenantId = $context->tenant->id;
            $slug = EventSlug::uniqueForTenant($tenantId, EventSlug::fromNames($nameEn, $nameAr));

            $clone = Event::query()->create([
                'tenant_id' => $tenantId,
                'slug' => $slug,
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'description_en' => $source->description_en,
                'description_ar' => $source->description_ar,
                'tier' => $source->tier,
                'event_type' => $source->event_type,
                'registration_mode' => $source->registration_mode,
                'status' => 'draft',
                'timezone' => $source->timezone,
                'start_at' => $source->start_at,
                'end_at' => $source->end_at,
                'registration_opens_at' => $source->registration_opens_at,
                'registration_closes_at' => $source->registration_closes_at,
                'location_name_en' => $source->location_name_en,
                'location_name_ar' => $source->location_name_ar,
                'location_address_en' => $source->location_address_en,
                'location_address_ar' => $source->location_address_ar,
                'capacity' => $source->capacity,
                'main_image_path' => null,
                'active_form_version_id' => null,
                'created_by_user_id' => $this->organizers->resolve($context),
                'published_by_user_id' => null,
                'published_at' => null,
            ]);

            if (is_string($source->main_image_path) && $source->main_image_path !== '') {
                $clone->forceFill([
                    'main_image_path' => $this->copyPublicFile(
                        $source->main_image_path,
                        "tenants/{$tenantId}/events/{$clone->id}/main",
                    ),
                ])->save();
            }

            $this->cloneImages($source, $clone);
            $this->cloneBranding($source, $clone, $nameEn, $nameAr);
            $venueIdMap = $this->cloneVenues($source, $clone);
            $this->cloneVenueMaps($source, $clone, $venueIdMap);
            $zoneIdMap = $this->cloneZones($source, $clone, $venueIdMap);
            $this->clonePaths($source, $clone, $venueIdMap, $zoneIdMap);
            $this->cloneAgenda($source, $clone, $venueIdMap, $zoneIdMap);
            $this->cloneCategories($source, $clone, $venueIdMap);
            $this->cloneRegistrationForm($source, $clone, $context);
            $this->cloneEmailTemplates($source, $clone);
            $this->cloneBadgeTemplates($source, $clone);

            $this->audit->writeTenant(
                'event.cloned',
                'succeeded',
                $context,
                targetType: 'event',
                targetId: $clone->id,
                metadata: ['source_event_id' => $source->id],
            );

            return $clone->refresh();
        });
    }

    private function cloneImages(Event $source, Event $clone): void
    {
        foreach ($source->images()->orderBy('sort_order')->orderBy('id')->get() as $image) {
            if (! is_string($image->path) || $image->path === '') {
                continue;
            }

            $copied = $this->copyPublicFile(
                $image->path,
                "tenants/{$clone->tenant_id}/events/{$clone->id}/gallery",
            );

            if ($copied === null) {
                continue;
            }

            EventImage::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'path' => $copied,
                'sort_order' => $image->sort_order,
            ]);
        }
    }

    private function cloneBranding(Event $source, Event $clone, string $nameEn, string $nameAr): void
    {
        $branding = $source->branding;
        if (! $branding instanceof EventBranding) {
            return;
        }

        EventBranding::query()->create([
            'tenant_id' => $clone->tenant_id,
            'event_id' => $clone->id,
            'brand_reference' => $this->uniqueBrandReference(
                (string) $clone->tenant_id,
                (string) ($branding->brand_reference ?: $clone->slug.'-brand'),
            ),
            'domain_reference' => $branding->domain_reference,
            'content_en' => $branding->content_en ?? [],
            'content_ar' => $branding->content_ar ?? [],
            'sender_name_en' => $nameEn,
            'sender_name_ar' => $nameAr,
            'status' => $branding->status ?: 'active',
            'theme_config' => $branding->theme_config ?? [],
        ]);
    }

    private function uniqueBrandReference(string $tenantId, string $base): string
    {
        $reference = $base;
        $suffix = 2;

        while (EventBranding::query()->where('tenant_id', $tenantId)->where('brand_reference', $reference)->exists()) {
            $reference = $base.'-'.$suffix;
            $suffix++;
        }

        return $reference;
    }

    /**
     * @return array<int, int> source event_venue_id => cloned event_venue_id
     */
    private function cloneVenues(Event $source, Event $clone): array
    {
        $map = [];

        foreach ($source->venues()->orderBy('sort_order')->orderBy('id')->get() as $venue) {
            $created = EventVenue::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'country_id' => $venue->country_id,
                'city_id' => $venue->city_id,
                'name_en' => $venue->name_en,
                'name_ar' => $venue->name_ar,
                'location_address' => $venue->location_address,
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'start_at' => $venue->start_at,
                'end_at' => $venue->end_at,
                'registration_opens_at' => $venue->registration_opens_at,
                'registration_closes_at' => $venue->registration_closes_at,
                'sort_order' => $venue->sort_order,
            ]);

            $map[(int) $venue->id] = (int) $created->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $venueIdMap
     */
    private function cloneVenueMaps(Event $source, Event $clone, array $venueIdMap): void
    {
        $maps = EventVenueMap::query()
            ->where('tenant_id', $source->tenant_id)
            ->where('event_id', $source->id)
            ->orderBy('id')
            ->get();

        foreach ($maps as $map) {
            $mappedVenueId = $venueIdMap[(int) $map->venue_id] ?? null;
            if ($mappedVenueId === null) {
                continue;
            }

            $copied = null;
            if ($map->image_path !== null && $map->image_path !== '') {
                $copied = $this->copyPublicFile(
                    (string) $map->image_path,
                    "tenants/{$clone->tenant_id}/events/{$clone->id}/venue-maps",
                );
                if ($copied === null) {
                    continue;
                }
            }

            EventVenueMap::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'venue_id' => $mappedVenueId,
                'image_path' => $copied ?? '',
                'width' => $map->width,
                'height' => $map->height,
                'overlay_opacity' => $map->overlay_opacity ?? 0.85,
                'remove_background' => (bool) ($map->remove_background ?? false),
                'show_base_map' => (bool) ($map->show_base_map ?? true),
                'map_center_lat' => $map->map_center_lat,
                'map_center_lng' => $map->map_center_lng,
                'map_zoom' => $map->map_zoom,
                'map_heading' => $map->map_heading,
                'map_type' => $map->map_type,
                'overlay_north' => $map->overlay_north,
                'overlay_south' => $map->overlay_south,
                'overlay_east' => $map->overlay_east,
                'overlay_west' => $map->overlay_west,
                'overlay_rotation' => $map->overlay_rotation ?? 0,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $venueIdMap
     * @return array<int, int> source zone_id => cloned zone_id
     */
    private function cloneZones(Event $source, Event $clone, array $venueIdMap): array
    {
        $map = [];

        foreach ($source->zones()->orderBy('id')->get() as $zone) {
            $mappedVenueId = $venueIdMap[(int) $zone->venue_id] ?? null;
            if ($mappedVenueId === null) {
                continue;
            }

            $created = EventZone::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'venue_id' => $mappedVenueId,
                'zone_name_en' => $zone->zone_name_en,
                'zone_name_ar' => $zone->zone_name_ar,
                'description_en' => $zone->description_en,
                'description_ar' => $zone->description_ar,
                'type' => $zone->type instanceof EventZoneType
                    ? $zone->type->value
                    : (string) $zone->type,
                'floor_type' => $zone->floor_type,
                'floor_number' => $zone->floor_number,
                'capacity' => $zone->capacity,
                'shape_type' => $zone->shape_type?->value ?? $zone->shape_type,
                'coordinate_space' => $zone->coordinate_space ?? 'relative',
                'polygon_coordinates' => $zone->polygon_coordinates,
                'shape_radius' => $zone->shape_radius,
                'shape_rotation' => $zone->shape_rotation ?? 0,
                'shape_radius_y' => $zone->shape_radius_y,
                'label' => $zone->label,
                'google_maps_url' => $zone->google_maps_url,
                'lat' => $zone->lat,
                'lng' => $zone->lng,
                'fill_color' => $zone->fill_color,
                'fill_image_path' => $zone->fill_image_path,
                'stroke_color' => $zone->stroke_color,
                'opacity' => $zone->opacity,
                'stroke_width' => $zone->stroke_width,
            ]);

            $map[(int) $zone->id] = (int) $created->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $venueIdMap
     * @param  array<int, int>  $zoneIdMap
     */
    private function clonePaths(Event $source, Event $clone, array $venueIdMap, array $zoneIdMap): void
    {
        $paths = EventPath::query()
            ->where('tenant_id', $source->tenant_id)
            ->where('event_id', $source->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($paths as $path) {
            $mappedVenueId = $venueIdMap[(int) $path->venue_id] ?? null;
            if ($mappedVenueId === null) {
                continue;
            }

            $fromZoneId = $path->from_zone_id !== null
                ? ($zoneIdMap[(int) $path->from_zone_id] ?? null)
                : null;
            $toZoneId = $path->to_zone_id !== null
                ? ($zoneIdMap[(int) $path->to_zone_id] ?? null)
                : null;

            EventPath::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'venue_id' => $mappedVenueId,
                'name_en' => $path->name_en,
                'name_ar' => $path->name_ar,
                'polyline_coordinates' => $path->polyline_coordinates,
                'coordinate_space' => $path->coordinate_space ?? 'relative',
                'from_zone_id' => $fromZoneId,
                'to_zone_id' => $toZoneId,
                'stroke_color' => $path->stroke_color,
                'stroke_width' => $path->stroke_width,
                'opacity' => $path->opacity,
                'sort_order' => $path->sort_order,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $venueIdMap
     * @param  array<int, int>  $zoneIdMap
     */
    private function cloneAgenda(Event $source, Event $clone, array $venueIdMap, array $zoneIdMap): void
    {

        foreach ($source->agendaItems()->orderBy('sort_order')->orderBy('id')->get() as $item) {
            $sourceVenueId = $item->event_venue_id !== null ? (int) $item->event_venue_id : null;
            $sourceZoneId = $item->zone_id !== null ? (int) $item->zone_id : null;

            EventAgendaItem::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'event_venue_id' => $sourceVenueId !== null ? ($venueIdMap[$sourceVenueId] ?? null) : null,
                'zone_id' => $sourceZoneId !== null ? ($zoneIdMap[$sourceZoneId] ?? null) : null,
                'agenda_date' => $item->agenda_date,
                'title_en' => $item->title_en,
                'title_ar' => $item->title_ar,
                'description_en' => $item->description_en,
                'description_ar' => $item->description_ar,
                'speaker' => $item->speaker,
                'start_at' => $item->start_at,
                'end_at' => $item->end_at,
                'sort_order' => $item->sort_order,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $venueIdMap
     */
    private function cloneCategories(Event $source, Event $clone, array $venueIdMap): void
    {
        $categories = EventCategory::query()
            ->where('event_id', $source->id)
            ->with(['privileges', 'venues.days'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($categories as $category) {
            $clonedCategory = EventCategory::query()->create([
                'event_id' => $clone->id,
                'category_template_id' => $category->category_template_id,
                'name' => $category->name,
                'name_ar' => $category->name_ar,
                'slug' => $category->slug,
                'color' => $category->color,
                'capacity' => $category->capacity,
                'is_paid' => $category->is_paid,
                'price_minor' => $category->price_minor,
                'currency' => $category->currency,
                'sort_order' => $category->sort_order,
            ]);

            foreach ($category->privileges as $privilege) {
                $clonedCategory->privileges()->create([
                    'key' => $privilege->key,
                    'label' => $privilege->label,
                    'label_ar' => $privilege->label_ar,
                    'effect' => $privilege->effect,
                    'target_type' => $privilege->target_type,
                    'target_id' => $privilege->target_id,
                ]);
            }

            foreach ($category->venues as $categoryVenue) {
                $mappedVenueId = $venueIdMap[(int) $categoryVenue->event_venue_id] ?? null;
                if ($mappedVenueId === null) {
                    continue;
                }

                $clonedCategoryVenue = $clonedCategory->venues()->create([
                    'event_venue_id' => $mappedVenueId,
                    'sort_order' => $categoryVenue->sort_order,
                ]);

                foreach ($categoryVenue->days as $day) {
                    $clonedCategoryVenue->days()->create([
                        'date' => $day->date,
                        'capacity' => $day->capacity,
                    ]);
                }
            }
        }
    }

    private function cloneRegistrationForm(Event $source, Event $clone, TenantContext $context): void
    {
        $sourceVersion = null;

        if ($source->active_form_version_id !== null) {
            $sourceVersion = RegistrationFormVersion::query()
                ->where('tenant_id', $source->tenant_id)
                ->where('event_id', $source->id)
                ->whereKey($source->active_form_version_id)
                ->first();
        }

        if ($sourceVersion === null) {
            $sourceVersion = RegistrationFormVersion::query()
                ->where('tenant_id', $source->tenant_id)
                ->where('event_id', $source->id)
                ->where('status', 'published')
                ->orderByDesc('version')
                ->first();
        }

        if ($sourceVersion === null) {
            return;
        }

        $form = RegistrationForm::query()->create([
            'tenant_id' => $clone->tenant_id,
            'event_id' => $clone->id,
            'name' => 'Registration form',
            'status' => 'active',
            'created_by_user_id' => $context->actor->id,
        ]);

        $version = RegistrationFormVersion::query()->create([
            'tenant_id' => $clone->tenant_id,
            'event_id' => $clone->id,
            'registration_form_id' => $form->id,
            'version' => 1,
            'status' => 'published',
            'fields' => $sourceVersion->fields ?? [],
            'schema_hash' => $sourceVersion->schema_hash,
            'privacy_notice_version' => $sourceVersion->privacy_notice_version,
            'terms_version' => $sourceVersion->terms_version,
            'published_by_user_id' => $context->actor->id,
            'published_at' => now(),
        ]);

        $clone->forceFill(['active_form_version_id' => $version->id])->save();
    }

    private function cloneEmailTemplates(Event $source, Event $clone): void
    {
        $sourceDir = "tenants/{$source->tenant_id}/events/{$source->id}/email-templates";
        $targetDir = "tenants/{$clone->tenant_id}/events/{$clone->id}/email-templates";
        $this->copyPublicDirectory($sourceDir, $targetDir);

        $templates = EventEmailTemplate::query()
            ->where('tenant_id', $source->tenant_id)
            ->where('event_id', $source->id)
            ->get();

        foreach ($templates as $template) {
            EventEmailTemplate::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'type' => $template->type,
                'subject_en' => $template->subject_en,
                'subject_ar' => $template->subject_ar,
                'html_body_en' => $this->rewriteEventStoragePaths(
                    (string) $template->html_body_en,
                    (string) $source->id,
                    (string) $clone->id,
                ),
                'html_body_ar' => $this->rewriteEventStoragePaths(
                    (string) $template->html_body_ar,
                    (string) $source->id,
                    (string) $clone->id,
                ),
            ]);
        }
    }

    private function cloneBadgeTemplates(Event $source, Event $clone): void
    {
        $templates = BadgeTemplate::query()
            ->where('tenant_id', $source->tenant_id)
            ->where('event_id', $source->id)
            ->orderBy('id')
            ->get();

        foreach ($templates as $template) {
            $backgroundPath = null;
            if (is_string($template->background_image_path) && $template->background_image_path !== '') {
                $backgroundPath = $this->copyPublicFile(
                    $template->background_image_path,
                    "tenants/{$clone->tenant_id}/events/{$clone->id}/badge-templates",
                );
            }

            BadgeTemplate::query()->create([
                'tenant_id' => $clone->tenant_id,
                'event_id' => $clone->id,
                'name' => $template->name,
                'layout' => $template->layout ?? [],
                'paper_size' => $template->paper_size,
                'printer_type' => $template->printer_type,
                'status' => $template->status,
                'background_color' => $template->background_color,
                'background_gradient' => $template->background_gradient,
                'background_image_path' => $backgroundPath,
                'orientation' => $template->orientation,
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
            ]);
        }
    }

    private function rewriteEventStoragePaths(string $html, string $sourceEventId, string $cloneEventId): string
    {
        if ($html === '') {
            return $html;
        }

        return str_replace(
            [
                'events/'.$sourceEventId.'/',
                'events%2F'.$sourceEventId.'%2F',
            ],
            [
                'events/'.$cloneEventId.'/',
                'events%2F'.$cloneEventId.'%2F',
            ],
            $html,
        );
    }

    private function copyPublicFile(string $sourcePath, string $targetDirectory): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($sourcePath)) {
            return null;
        }

        $filename = basename($sourcePath);
        $targetPath = rtrim($targetDirectory, '/').'/'.$filename;
        $disk->makeDirectory($targetDirectory);

        if ($disk->exists($targetPath)) {
            $targetPath = rtrim($targetDirectory, '/').'/'.Str::ulid().'-'.$filename;
        }

        $disk->copy($sourcePath, $targetPath);

        return $targetPath;
    }

    private function copyPublicDirectory(string $sourceDir, string $targetDir): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($sourceDir)) {
            return;
        }

        $disk->makeDirectory($targetDir);

        foreach ($disk->allFiles($sourceDir) as $file) {
            $relative = Str::after($file, rtrim($sourceDir, '/').'/');
            $target = rtrim($targetDir, '/').'/'.$relative;
            $disk->makeDirectory(dirname($target));
            if (! $disk->exists($target)) {
                $disk->copy($file, $target);
            }
        }
    }
}
