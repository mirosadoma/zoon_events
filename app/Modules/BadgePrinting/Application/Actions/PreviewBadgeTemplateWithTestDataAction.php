<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;

final readonly class PreviewBadgeTemplateWithTestDataAction
{
    public function __construct(
        private RenderBadgePngAction $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $fieldValues
     * @return array{png_base64: string, mime: string}|null
     */
    public function execute(BadgeTemplate $template, array $fieldValues, ?string $qrPayload = null): ?array
    {
        $normalized = [];
        foreach ($fieldValues as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (is_string($value) || is_numeric($value)) {
                $normalized[$key] = trim((string) $value);
            } elseif ($value === null) {
                $normalized[$key] = null;
            }
        }

        $qr = $qrPayload ?? (is_string($normalized['qr'] ?? null) ? $normalized['qr'] : 'PREVIEW-QR-TEST');
        $png = $this->renderer->execute($template, $normalized, $qr);

        if ($png === null) {
            return null;
        }

        return [
            'png_base64' => base64_encode($png),
            'mime' => 'image/png',
        ];
    }
}
