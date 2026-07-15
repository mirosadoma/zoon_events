<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Application\Actions\ChangeTenantStatus;
use App\Modules\Tenancy\Application\Actions\CreateTenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Http\Request;

class PlatformTenantController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly CreateTenant $createTenant,
        private readonly ChangeTenantStatus $changeTenantStatus,
        private readonly CursorPaginator $paginator,
    ) {}

    public function index(Request $request)
    {
        $page = $this->paginator->paginate(Tenant::query(), 'platform:tenants', [], $request->string('cursor')->toString(), $request->integer('page_size', 50));

        return $this->success(
            collect($page->items)->map(fn (Tenant $tenant): array => $this->mapTenant($tenant))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function show(string $tenantId)
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        return $this->success($this->mapTenant($tenant));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:tenants,slug'],
            'organization_type' => ['required', 'in:organizer,venue_owner,hybrid'],
            'default_locale' => ['required', 'in:en,ar'],
            'timezone' => ['required', 'string', 'max:64'],
            'data_residency_region' => ['required', 'string', 'max:64'],
            'initial_admin_user_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $tenant = $this->createTenant->handle($validated, $actor);

        return $this->success($this->mapTenant($tenant), 201);
    }

    public function update(Request $request, string $tenantId)
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', 'in:active,suspended,deactivated'],
            'organization_type' => ['sometimes', 'in:organizer,venue_owner,hybrid'],
            'default_locale' => ['sometimes', 'in:en,ar'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'data_residency_region' => ['sometimes', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $tenant = $this->changeTenantStatus->handle(
            $tenant,
            collect($validated)->except('reason')->all(),
            $actor,
            $validated['reason'],
        );

        return $this->success($this->mapTenant($tenant));
    }

    private function mapTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status->value,
            'organization_type' => $tenant->organization_type->value,
            'default_locale' => $tenant->default_locale,
            'timezone' => $tenant->timezone,
            'data_residency_region' => $tenant->data_residency_region,
            'created_at' => $tenant->created_at?->toIso8601String(),
        ];
    }
}
