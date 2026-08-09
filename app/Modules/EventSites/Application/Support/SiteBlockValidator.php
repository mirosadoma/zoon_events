<?php

namespace App\Modules\EventSites\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Domain\SiteBlockType;

final class SiteBlockValidator
{
    private const MAX_BLOCKS = 80;

    private const MAX_BLOCK_CHARS = 20000;

    /**
     * Validate blocks array and return field paths for 422 errors.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, list<string>>
     */
    public function validate(array $blocks): array
    {
        $errors = [];
        $seenIds = [];

        if (count($blocks) > self::MAX_BLOCKS) {
            $errors['blocks'] = [__('event_sites.validation.too_many_blocks', ['max' => self::MAX_BLOCKS])];
        }

        foreach ($blocks as $index => $block) {
            $blockErrors = $this->validateBlock($block, $index, $seenIds);
            foreach ($blockErrors as $path => $messages) {
                $errors[$path] = array_merge($errors[$path] ?? [], $messages);
            }

            $blockId = $block['id'] ?? null;
            if ($blockId !== null) {
                $seenIds[$blockId] = true;
            }
        }

        return $errors;
    }

    /**
     * Check publishing blockers for the site.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return list<string>
     */
    public function publishBlockers(array $blocks, Event $event): array
    {
        $blockers = [];

        $visibleBlocks = array_filter($blocks, static fn (array $b): bool => ($b['visible'] ?? true) === true);

        if (count($visibleBlocks) === 0) {
            $blockers[] = 'no_visible_blocks';
        }

        $registerBlock = $this->findBlockByType($visibleBlocks, SiteBlockType::RegisterCta->value);
        if ($registerBlock !== null) {
            $registrationAvailable = in_array($event->status, [
                'published',
                'registration_open',
                'registration_closed',
                'live',
            ], true);

            if (! $registrationAvailable) {
                $blockers[] = 'register_target_invalid';
            }
        }

        return $blockers;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, bool>  $seenIds
     * @return array<string, list<string>>
     */
    private function validateBlock(array $block, int $index, array $seenIds): array
    {
        $errors = [];
        $prefix = "blocks.{$index}";

        $id = $block['id'] ?? null;
        if ($id !== null && isset($seenIds[$id])) {
            $errors["{$prefix}.id"] = [__('event_sites.validation.duplicate_block_id')];
        }

        $type = $block['type'] ?? null;
        if ($type === null || ! SiteBlockType::isValid($type)) {
            $errors["{$prefix}.type"] = [__('event_sites.validation.unknown_block_type')];

            return $errors;
        }

        $allowedOptions = SiteBlockSchema::optionKeysFor($type);
        $options = $block['options'] ?? [];
        if (is_array($options)) {
            foreach (array_keys($options) as $optionKey) {
                if (! in_array($optionKey, $allowedOptions, true)) {
                    $errors["{$prefix}.options.{$optionKey}"] = [__('event_sites.validation.unknown_option')];
                }
            }
        }

        foreach (['content_en', 'content_ar'] as $contentKey) {
            $content = $block[$contentKey] ?? [];
            if (is_array($content)) {
                $totalChars = $this->countContentChars($content);
                if ($totalChars > self::MAX_BLOCK_CHARS) {
                    $errors["{$prefix}.{$contentKey}"] = [__('event_sites.validation.block_too_long')];
                }
            }
        }

        return $errors;
    }

    /** @param  array<string, mixed>  $content */
    private function countContentChars(array $content): int
    {
        $total = 0;
        foreach ($content as $value) {
            if (is_string($value)) {
                $total += mb_strlen($value);
            } elseif (is_array($value)) {
                $total += mb_strlen(json_encode($value, JSON_THROW_ON_ERROR));
            }
        }

        return $total;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private function findBlockByType(array $blocks, string $type): ?array
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === $type) {
                return $block;
            }
        }

        return null;
    }
}
