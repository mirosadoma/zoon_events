<?php

namespace App\Modules\AdminConsole\Http\Controllers\Concerns;

trait ResolvesRouteParam
{
    private function routeParam(string $name): string
    {
        $value = $this->routeParamOrNull($name);

        abort_unless(is_string($value) && $value !== '', 404);

        return $value;
    }

    private function routeParamOrNull(string $name): ?string
    {
        $value = request()->route($name);

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
