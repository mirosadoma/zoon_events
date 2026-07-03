<?php

namespace Tests\Feature\Shared;

use App\Modules\Shared\Domain\Context\RequestContextStore;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RequestPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'request.context', 'locale'])->get('/_test/request-context', function (RequestContextStore $store) {
            return response()->json([
                'correlation_id' => $store->current()?->correlationId->value,
                'locale' => app()->getLocale(),
            ]);
        });
    }

    #[Test]
    public function request_context_header_is_returned_and_store_is_cleared(): void
    {
        $first = $this->withHeader('X-Correlation-ID', 'request-context-1')->get('/_test/request-context');

        $first->assertOk()
            ->assertHeader('X-Correlation-ID', 'request-context-1')
            ->assertJsonPath('correlation_id', 'request-context-1');

        $this->assertNull(app(RequestContextStore::class)->current());

        $second = $this->withHeader('X-Correlation-ID', 'request-context-2')->get('/_test/request-context');

        $second->assertOk()
            ->assertHeader('X-Correlation-ID', 'request-context-2')
            ->assertJsonPath('correlation_id', 'request-context-2');

        $this->assertNull(app(RequestContextStore::class)->current());
    }

    #[Test]
    public function locale_is_negotiated_from_accept_language_and_falls_back_safely(): void
    {
        $arabic = $this->withHeader('Accept-Language', 'ar-SA,ar;q=0.9,en;q=0.8')->get('/_test/request-context');

        $arabic->assertOk()->assertJsonPath('locale', 'ar');

        $fallback = $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/_test/request-context');

        $fallback->assertOk()->assertJsonPath('locale', 'en');
    }
}
