<?php

namespace App\Modules\Notifications\Application\Rendering;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

final class QrCodeImageDataUri
{
    public function pngBytesFromPayload(string $payload, int $size = 300): ?string
    {
        if ($payload === '') {
            return null;
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        return (new Writer(new GDLibRenderer($size)))->writeString($payload);
    }

    public function fromPayload(string $payload, int $size = 300): ?string
    {
        $png = $this->pngBytesFromPayload($payload, $size);

        return $png === null ? null : 'data:image/png;base64,'.base64_encode($png);
    }
}
