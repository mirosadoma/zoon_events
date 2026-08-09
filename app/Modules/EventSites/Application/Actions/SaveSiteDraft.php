<?php

namespace App\Modules\EventSites\Application\Actions;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Application\Support\SiteBlockValidator;
use App\Modules\EventSites\Infrastructure\Persistence\Models\EventSite;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveSiteDraft
{
    public function __construct(
        private SiteBlockValidator $validator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $settings
     * @return array{
     *   draft_revision: int,
     *   draft_blocks: array<int, array<string, mixed>>,
     *   settings: array<string, mixed>
     * }
     */
    public function execute(
        TenantContext $context,
        Event $event,
        int $expectedRevision,
        array $blocks,
        array $settings,
    ): array {
        $validationErrors = $this->validator->validate($blocks);
        if ($validationErrors !== []) {
            throw ValidationException::withMessages($validationErrors);
        }

        return DB::transaction(function () use ($context, $event, $expectedRevision, $blocks, $settings): array {
            $site = EventSite::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if ($site === null) {
                $site = EventSite::query()->create([
                    'tenant_id' => $context->tenant->id,
                    'event_id' => $event->id,
                    'status' => 'draft',
                    'page_mode' => 'single',
                    'draft_blocks' => $blocks,
                    'settings' => $settings,
                    'draft_updated_by_user_id' => $context->actor->id,
                    'draft_revision' => 1,
                ]);

                return [
                    'draft_revision' => $site->draft_revision,
                    'draft_blocks' => $site->draft_blocks,
                    'settings' => $site->settings ?? [],
                ];
            }

            if ($site->draft_revision !== $expectedRevision) {
                throw Phase1Problem::make('event_site.stale_revision', [
                    'current_revision' => $site->draft_revision,
                    'expected_revision' => $expectedRevision,
                ]);
            }

            $pageMode = (string) ($settings['page_mode'] ?? $site->page_mode ?? 'single');
            if (! in_array($pageMode, ['single', 'multi'], true)) {
                $pageMode = 'single';
            }

            $normalizedBlocks = array_map(static function (array $block): array {
                if (! isset($block['page_id']) || ! is_string($block['page_id']) || $block['page_id'] === '') {
                    $block['page_id'] = 'home';
                }

                return $block;
            }, $blocks);

            $site->forceFill([
                'draft_blocks' => $normalizedBlocks,
                'settings' => $settings,
                'page_mode' => $pageMode,
                'draft_updated_by_user_id' => $context->actor->id,
                'draft_revision' => $site->draft_revision + 1,
            ])->save();

            return [
                'draft_revision' => $site->draft_revision,
                'draft_blocks' => $site->draft_blocks,
                'settings' => $site->settings ?? [],
                'page_mode' => $site->page_mode,
            ];
        });
    }
}
