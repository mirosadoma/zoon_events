<?php

namespace App\Modules\EventSites\Application\Actions;

use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteFormSubmission;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSiteVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitSiteForm
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: int}
     */
    public function execute(
        int $tenantId,
        int $eventId,
        string $blockId,
        string $pageId,
        string $locale,
        array $payload,
        ?string $visitorHash,
    ): array {
        $site = EventSite::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'published')
            ->first();

        if ($site === null || $site->live_version_id === null) {
            abort(404);
        }

        $version = EventSiteVersion::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('id', $site->live_version_id)
            ->first();

        if ($version === null) {
            abort(404);
        }

        $blocks = is_array($version->blocks) ? $version->blocks : [];
        $formBlock = null;
        foreach ($blocks as $block) {
            if (($block['id'] ?? null) === $blockId && ($block['type'] ?? null) === 'form' && ($block['visible'] ?? true)) {
                $formBlock = $block;
                break;
            }
        }

        if ($formBlock === null) {
            abort(404);
        }

        $fields = $formBlock['content_'.$locale]['fields']
            ?? $formBlock['content_en']['fields']
            ?? [];

        if (! is_array($fields)) {
            $fields = [];
        }

        $cleaned = [];
        $errors = [];
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = (string) ($field['name'] ?? "field_{$index}");
            $label = (string) ($field['label'] ?? $name);
            $required = (bool) ($field['required'] ?? false);
            $value = $payload[$name] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($required && ($value === null || $value === '')) {
                $errors[$name] = ["{$label} is required."];

                continue;
            }
            if ($value !== null && $value !== '') {
                $cleaned[$name] = [
                    'label' => $label,
                    'value' => is_scalar($value) ? (string) $value : json_encode($value),
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($cleaned === []) {
            throw ValidationException::withMessages(['form' => ['Please fill at least one field.']]);
        }

        $settings = is_array($site->settings) ? $site->settings : [];
        $pages = is_array($settings['pages'] ?? null) ? $settings['pages'] : [];
        $pageTitle = $pageId;
        foreach ($pages as $page) {
            if (is_array($page) && ($page['id'] ?? null) === $pageId) {
                $pageTitle = (string) ($page['title_en'] ?? $page['slug'] ?? $pageId);
                break;
            }
        }

        $formName = (string) (
            $formBlock['content_'.$locale]['title']
            ?? $formBlock['content_en']['title']
            ?? 'Form'
        );

        $submission = DB::transaction(function () use (
            $tenantId,
            $eventId,
            $site,
            $pageId,
            $pageTitle,
            $blockId,
            $formName,
            $cleaned,
            $visitorHash,
            $locale,
        ): EventSiteFormSubmission {
            return EventSiteFormSubmission::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'event_site_id' => $site->id,
                'page_id' => $pageId,
                'page_title' => $pageTitle,
                'block_id' => $blockId,
                'form_name' => $formName,
                'payload' => $cleaned,
                'visitor_hash' => $visitorHash,
                'locale' => $locale,
            ]);
        });

        return ['id' => (int) $submission->id];
    }
}
