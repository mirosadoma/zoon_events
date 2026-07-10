<?php

namespace Tests\Contract;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Shared\Http\Problems\ProblemFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('shared')]
final class FoundationProblemMappingTest extends TestCase
{
    public function test_csrf_token_mismatch_maps_to_419(): void
    {
        $problem = ProblemFactory::fromThrowable(
            new TokenMismatchException('CSRF token mismatch.'),
            '/api/v1/tenant/events/1/badge-templates/1',
            'correlation',
        );

        self::assertSame(419, $problem->status);
        self::assertSame('csrf_token_mismatch', $problem->code);
    }

    public function test_model_not_found_maps_to_404(): void
    {
        $problem = ProblemFactory::fromThrowable(
            (new ModelNotFoundException)->setModel(BadgeTemplate::class),
            '/api/v1/tenant/events/1/badge-templates/99',
            'correlation',
        );

        self::assertSame(404, $problem->status);
        self::assertSame('resource_not_found', $problem->code);
    }
}
