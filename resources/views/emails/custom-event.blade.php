@php
    $rendered = $htmlBody ?? '';
    if (is_string($rendered) && $rendered !== '' && ! empty($inlineImages) && isset($message)) {
        foreach ($inlineImages as $image) {
            if (! is_array($image) || empty($image['bytes']) || empty($image['cid'])) {
                continue;
            }
            $embeddedSrc = $message->embedData(
                $image['bytes'],
                $image['filename'] ?? ($image['cid'].'.png'),
                $image['mime'] ?? 'image/png',
            );
            $rendered = str_replace('cid:'.$image['cid'], $embeddedSrc, $rendered);
        }
    }
@endphp
{!! $rendered !!}
