<?php

namespace Tests\Feature\Shared;

use App\Exceptions\FoundationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AssertsProblemDetails;
use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    use AssertsProblemDetails;

    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('/api/v1/_test/problems')->group(function (): void {
            Route::get('/unauthenticated', fn () => throw new AuthenticationException);
            Route::get('/forbidden', fn () => abort(403));
            Route::get('/not-found', fn () => abort(404));
            Route::get('/conflict', fn () => throw FoundationException::conflict('resource_conflict', 'A conflicting resource already exists.'));
            Route::get('/validation', function () {
                Validator::make([], [
                    'name' => ['required'],
                ])->validate();
            });
            Route::get('/rate-limited', fn () => abort(429));
            Route::get('/service-unavailable', fn () => throw new HttpException(503, 'Unavailable'));
            Route::get('/invalid-server-configuration', fn () => throw new \InvalidArgumentException('Credential signing key is unavailable.'));
        });
    }

    #[Test]
    public function standard_api_errors_follow_problem_details_contract(): void
    {
        $cases = [
            '/api/v1/_test/problems/unauthenticated' => [401, 'unauthenticated'],
            '/api/v1/_test/problems/forbidden' => [403, 'forbidden'],
            '/api/v1/_test/problems/not-found' => [404, 'resource_not_found'],
            '/api/v1/_test/problems/conflict' => [409, 'resource_conflict'],
            '/api/v1/_test/problems/validation' => [422, 'validation_failed'],
            '/api/v1/_test/problems/rate-limited' => [429, 'rate_limited'],
            '/api/v1/_test/problems/service-unavailable' => [503, 'service_unavailable'],
            '/api/v1/_test/problems/invalid-server-configuration' => [503, 'service_unavailable'],
        ];

        foreach ($cases as $path => [$status, $code]) {
            $response = $this->withHeader('X-Correlation-ID', 'problem-test')->getJson($path);

            $this->assertProblemDetails($response, $status, $code);

            $response->assertHeader('X-Correlation-ID', 'problem-test')
                ->assertJsonPath('correlation_id', 'problem-test')
                ->assertJsonMissingPath('trace');
        }
    }

    #[Test]
    public function unmatched_and_method_not_allowed_requests_keep_problem_contract_and_correlation(): void
    {
        $notFound = $this->withHeader('X-Correlation-ID', '***bad***')->getJson('/api/v1/does-not-exist');

        $this->assertProblemDetails($notFound, 404, 'resource_not_found');
        $this->assertNotEmpty($notFound->headers->get('X-Correlation-ID'));
        $this->assertSame($notFound->headers->get('X-Correlation-ID'), $notFound->json('correlation_id'));

        $methodNotAllowed = $this->postJson('/api/v1/_test/problems/not-found');

        $this->assertProblemDetails($methodNotAllowed, 405, 'method_not_allowed');
        $this->assertNotEmpty($methodNotAllowed->headers->get('X-Correlation-ID'));
    }
}
