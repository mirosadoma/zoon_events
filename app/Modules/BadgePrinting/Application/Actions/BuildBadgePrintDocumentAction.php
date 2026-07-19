<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Notifications\Application\Rendering\QrCodeImageDataUri;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final readonly class BuildBadgePrintDocumentAction
{
    public function __construct(
        private RenderBadgePrintPayloadAction $payload,
        private RenderBadgeEmailHtmlAction $html,
        private QrCodeImageDataUri $qrImages,
    ) {}

    /**
     * @param  array<string, string|null>  $fieldOverrides
     * @return array{html: string, fields: array<string, string|null>, editable_fields: list<string>}
     */
    public function build(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        BadgeTemplate $template,
        array $fieldOverrides = [],
        bool $autoPrint = true,
    ): array {
        $printPayload = $this->payload->execute(
            $tenantId,
            $eventId,
            $attendeeId,
            $credentialId,
            $template,
        );

        $fields = [];
        foreach ($printPayload->fields as $key => $value) {
            $fields[(string) $key] = is_scalar($value) || $value === null ? ($value !== null ? (string) $value : null) : null;
        }

        foreach ($fieldOverrides as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            $trimmed = trim((string) $value);
            $fields[$key] = $trimmed !== '' ? $trimmed : null;
        }

        // Template custom_text fallback when no override/value was provided.
        if (($fields['custom_text'] ?? null) === null) {
            $fields['custom_text'] = $this->defaultCustomText($template);
        }

        foreach (['organizer_logo_ref', 'sponsor_logo_ref'] as $imageField) {
            if (isset($fields[$imageField]) && is_string($fields[$imageField])) {
                $fields[$imageField] = $this->embedImage($fields[$imageField]) ?? $fields[$imageField];
            }
        }

        $qrPayload = is_string($fields['qr'] ?? null) ? $fields['qr'] : null;
        $qrDataUri = $qrPayload !== null ? $this->qrImages->fromPayload($qrPayload, 360) : null;
        $badgeHtml = $this->html->execute(
            $this->withEmbeddedBackground($template),
            $fields,
            $qrDataUri,
            showFieldGuides: ! $autoPrint,
        );

        return [
            'html' => $this->wrapDocument($badgeHtml, (string) ($template->name ?: 'Badge'), $autoPrint),
            'fields' => $fields,
            'editable_fields' => $this->editableFields($template),
        ];
    }

    /**
     * @param  array<string, string|null>  $fieldOverrides
     */
    public function execute(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $credentialId,
        BadgeTemplate $template,
        array $fieldOverrides = [],
        bool $autoPrint = true,
    ): string {
        return $this->build(
            $tenantId,
            $eventId,
            $attendeeId,
            $credentialId,
            $template,
            $fieldOverrides,
            $autoPrint,
        )['html'];
    }

    /** @return list<string> */
    private function editableFields(BadgeTemplate $template): array
    {
        $layout = (array) $template->layout;
        $keys = [];

        if (array_is_list($layout)) {
            foreach ($layout as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $field = $item['field'] ?? null;
                if (is_string($field) && in_array($field, ['job_title', 'custom_text', 'company'], true)) {
                    $keys[] = $field;
                }
            }
        } else {
            foreach (array_keys($layout) as $field) {
                if (in_array((string) $field, ['job_title', 'custom_text', 'company'], true)) {
                    $keys[] = (string) $field;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function defaultCustomText(BadgeTemplate $template): ?string
    {
        $layout = (array) $template->layout;
        if (! array_is_list($layout)) {
            return null;
        }

        foreach ($layout as $item) {
            if (! is_array($item) || ($item['field'] ?? null) !== 'custom_text') {
                continue;
            }
            if (is_string($item['text'] ?? null) && trim((string) $item['text']) !== '') {
                return trim((string) $item['text']);
            }
        }

        return null;
    }

    private function withEmbeddedBackground(BadgeTemplate $template): BadgeTemplate
    {
        $path = $template->background_image_path;
        if (! is_string($path) || trim($path) === '') {
            return $template;
        }

        if (str_starts_with($path, 'data:')) {
            return $template;
        }

        $absolute = Storage::disk('public')->path($path);
        if (! is_file($absolute)) {
            return $template;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';
        $dataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));

        $clone = clone $template;
        $clone->background_image_path = $dataUri;

        return $clone;
    }

    private function embedImage(string $src): ?string
    {
        if (str_starts_with($src, 'data:')) {
            return $src;
        }

        try {
            if (preg_match('#^https?://#i', $src) === 1) {
                $response = Http::timeout(5)->get($src);
                if (! $response->successful()) {
                    return null;
                }
                $mime = $response->header('Content-Type') ?: 'image/png';
                $mime = explode(';', $mime)[0];

                return 'data:'.$mime.';base64,'.base64_encode($response->body());
            }

            $path = parse_url($src, PHP_URL_PATH);
            if (! is_string($path) || $path === '') {
                return null;
            }

            $relative = ltrim(preg_replace('#^/storage/#', '', $path) ?? $path, '/');
            $absolute = Storage::disk('public')->path($relative);
            if (! is_file($absolute)) {
                return null;
            }

            $mime = mime_content_type($absolute) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));
        } catch (\Throwable) {
            return null;
        }
    }

    private function wrapDocument(string $badgeHtml, string $title, bool $autoPrint): string
    {
        $safeTitle = e($title);
        $printScript = $autoPrint
            ? <<<'HTML'
  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.focus();
        window.print();
      }, 180);
    });
  </script>
HTML
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle}</title>
  <style>
    @page { margin: 8mm; size: auto; }
    html, body {
      margin: 0;
      padding: 0;
      background: #fff;
      color: #0f172a;
      font-family: Arial, Helvetica, sans-serif;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .badge-print-sheet {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 16px;
      box-sizing: border-box;
    }
    .badge-print-sheet > div {
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
    }
    .badge-print-sheet img {
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
      max-width: none !important;
    }
    @media print {
      html, body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .badge-print-sheet {
        min-height: auto;
        padding: 0;
      }
      .badge-print-sheet > div {
        box-shadow: none;
        border: 0 !important;
      }
    }
  </style>
</head>
<body>
  <div class="badge-print-sheet">{$badgeHtml}</div>
  {$printScript}
</body>
</html>
HTML;
    }
}
