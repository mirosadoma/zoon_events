<?php

namespace Tests\Contract\Phase1;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
final class Phase1OpenApiCoverageTest extends TestCase
{
    public function test_all_twenty_nine_review_operations_have_runtime_routes(): void
    {
        $documented = $this->operations();
        self::assertCount(29, $documented);
        self::assertCount(29, array_unique(array_column($documented, 'operation')));

        $runtime = collect(app('router')->getRoutes()->getRoutes())->flatMap(function ($route): array {
            return collect(array_diff($route->methods(), ['HEAD', 'OPTIONS']))->map(
                fn (string $method): string => $method.' /'.preg_replace('/^api\/v1\/?/', '', $route->uri()),
            )->all();
        });
        foreach ($documented as $operation) {
            self::assertTrue($runtime->contains($operation['route']), $operation['operation'].' has no runtime route');
        }
        $phaseOneRuntime = $runtime->filter(function (string $route): bool {
            if (preg_match(
                '#^[A-Z]+ /(public/(events|orders)|tenant/events|tenant/credential-validations|webhooks/(payments|notifications))#',
                $route,
            ) !== 1) {
                return false;
            }

            return preg_match(
                '#/(wallet-passes|check-in|check-in-summary|offline-allowlist|offline-scan-batches|scans)(/|$)#',
                $route,
            ) !== 1;
        })->sort()->values()->all();
        $documentedRoutes = collect($documented)->pluck('route')->sort()->values()->all();
        self::assertSame($documentedRoutes, $phaseOneRuntime, 'Phase 1 has undocumented or stale runtime routes.');
    }

    public function test_contract_has_success_and_problem_responses_for_each_operation(): void
    {
        $yaml = file_get_contents(base_path('specs/002-registration-ticketing-credentials/contracts/openapi.yaml'));
        self::assertIsString($yaml);
        foreach ($this->operations() as $operation) {
            self::assertMatchesRegularExpression(
                '/operationId:\\s*'.preg_quote($operation['operation'], '/').'.*?responses:.*?[\'"]?(200|201|202)[\'"]?:.*?[\'"]?(400|401|403|404|409|422)[\'"]?:/s',
                $yaml,
                $operation['operation'].' lacks success/problem compatibility responses',
            );
        }
    }

    /** @return list<array{operation:string,route:string}> */
    private function operations(): array
    {
        $lines = file(base_path('specs/002-registration-ticketing-credentials/contracts/openapi.yaml'), FILE_IGNORE_NEW_LINES);
        $result = [];
        $path = $method = null;
        foreach ($lines as $line) {
            if (preg_match('/^  (\/[^:]+):$/', $line, $match)) {
                $path = $match[1];
            } elseif (preg_match('/^    (get|post|patch|put|delete):$/', $line, $match)) {
                $method = strtoupper($match[1]);
            } elseif ($path && $method && preg_match('/^      operationId: (\S+)$/', $line, $match)) {
                $result[] = ['operation' => $match[1], 'route' => $method.' '.$path];
            }
        }

        return $result;
    }
}
