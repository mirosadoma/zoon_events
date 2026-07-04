<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head><meta charset="utf-8"><title>{{ __('phase1.confirmation_title', [], $locale) }}</title></head>
<body>
<main>
    <h1>{{ __('phase1.confirmation_title', [], $locale) }}</h1>
    <p>{{ __('phase1.confirmation_event', ['event' => $event_name], $locale) }}</p>
    <p>{{ __('phase1.confirmation_order', ['order' => $order_reference], $locale) }}</p>
    <p><a href="{{ $credential_url }}">{{ __('phase1.view_credential', [], $locale) }}</a></p>
</main>
</body>
</html>
