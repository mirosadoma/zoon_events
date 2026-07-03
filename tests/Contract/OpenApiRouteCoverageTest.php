<?php

namespace Tests\Contract;

use Tests\TestCase;

class OpenApiRouteCoverageTest extends TestCase
{
    public function test_every_public_api_operation_is_documented_and_implemented(): void
    {
        $lines = file(base_path('specs/001-project-foundation/contracts/openapi.yaml'), FILE_IGNORE_NEW_LINES);
        $documented = [];
        $path = null;
        foreach ($lines as $line) {
            if (preg_match('/^  (\\/[^:]+):$/', $line, $match)) {
                $path = $match[1];
            } elseif ($path && preg_match('/^    (get|post|put|patch|delete):$/', $line, $match)) {
                $documented[] = strtoupper($match[1]).' '.$path;
            }
        }

        $runtime = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || str_contains($route->uri(), '__probe')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $runtime[] = $method.' /'.ltrim(substr($route->uri(), strlen('api/v1')), '/');
            }
        }

        sort($documented);
        sort($runtime);
        self::assertSame($documented, $runtime);
    }
}
