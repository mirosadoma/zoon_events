<?php

namespace Tests\Feature\AdminConsole;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class ErrorPageTest extends Phase1MySqlTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('{locale}')
            ->where(['locale' => 'en|ar'])
            ->group(function (): void {
                Route::get('/_test/errors/unauthorized', fn () => abort(401));
                Route::get('/_test/errors/forbidden', fn () => throw new AuthorizationException);
                Route::get('/_test/errors/method-only', fn () => 'ok');
                Route::post('/_test/errors/session-expired', fn () => throw new TokenMismatchException);
                Route::get('/_test/errors/rate-limited', fn () => abort(429));
                Route::get('/_test/errors/unavailable', fn () => throw new HttpException(503, 'Unavailable'));
            });
    }

    public function test_unknown_web_route_renders_localized_not_found_page_in_english(): void
    {
        $this->get('/en/does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('errors/HttpError')
                ->where('statusCode', 404));
    }

    public function test_unknown_web_route_renders_localized_not_found_page_in_arabic(): void
    {
        $this->get('/ar/does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('errors/HttpError')
                ->where('statusCode', 404));
    }

    public function test_api_not_found_still_returns_problem_json(): void
    {
        $this->getJson('/api/v1/platform/_placeholder')
            ->assertNotFound()
            ->assertJsonPath('code', 'resource_not_found');
    }

    public function test_web_error_pages_render_branded_inertia_shell(): void
    {
        $cases = [
            '/en/_test/errors/unauthorized' => 401,
            '/en/_test/errors/forbidden' => 403,
            '/en/_test/errors/rate-limited' => 429,
            '/en/_test/errors/unavailable' => 503,
        ];

        foreach ($cases as $path => $status) {
            $this->get($path)
                ->assertStatus($status)
                ->assertInertia(fn ($page) => $page
                    ->component('errors/HttpError')
                    ->where('statusCode', $status));
        }
    }

    public function test_method_not_allowed_renders_custom_error_page(): void
    {
        $this->post('/en/_test/errors/method-only')
            ->assertStatus(405)
            ->assertInertia(fn ($page) => $page
                ->component('errors/HttpError')
                ->where('statusCode', 405));
    }

    public function test_session_expired_renders_custom_error_page(): void
    {
        $this->post('/en/_test/errors/session-expired')
            ->assertStatus(419)
            ->assertInertia(fn ($page) => $page
                ->component('errors/HttpError')
                ->where('statusCode', 419));
    }
}
