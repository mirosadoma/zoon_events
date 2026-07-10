<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Domain\Context\CorrelationId;
use App\Modules\Shared\Domain\Context\RequestContext;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Domain\Context\RequestId;
use App\Modules\Shared\Domain\Locale;
use App\Modules\Shared\Http\Resources\ApiCollection;
use App\Modules\Shared\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiResourceTest extends TestCase
{
    #[Test]
    public function resource_envelope_includes_correlation_metadata(): void
    {
        app(RequestContextStore::class)->set(new RequestContext(
            CorrelationId::fromHeader('resource-test'),
            RequestId::generate(),
            Locale::from('en'),
        ));

        $resource = new class(['role_name' => 'Tenant Administrator']) extends ApiResource {};

        $resolved = $resource->toResponse(new Request)->getData(true);

        $this->assertSame('Tenant Administrator', $resolved['data']['role_name']);
        $this->assertSame('resource-test', $resolved['meta']['correlation_id']);

        app(RequestContextStore::class)->clear();
    }

    #[Test]
    public function collection_envelope_uses_snake_case_metadata(): void
    {
        app(RequestContextStore::class)->set(new RequestContext(
            CorrelationId::fromHeader('collection-test'),
            RequestId::generate(),
            Locale::from('en'),
        ));

        $collection = new ApiCollection(collect([
            ['display_name' => 'Platform Administrator'],
        ]));

        $resolved = $collection->toResponse(new Request)->getData(true);

        $this->assertSame('collection-test', $resolved['meta']['correlation_id']);
        $this->assertArrayHasKey('data', $resolved);

        app(RequestContextStore::class)->clear();
    }
}
