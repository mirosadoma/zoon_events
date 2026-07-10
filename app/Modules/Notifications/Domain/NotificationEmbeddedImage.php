<?php

namespace App\Modules\Notifications\Domain;

final readonly class NotificationEmbeddedImage
{
    public function __construct(
        public string $contentId,
        public string $mimeType,
        public string $content,
    ) {}
}
