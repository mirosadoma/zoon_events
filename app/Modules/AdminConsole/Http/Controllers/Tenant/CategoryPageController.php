<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Domain\CategoryLockStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplatePrivilege;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\Privilege;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryPageController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
    ) {}

    public function index(): Response
    {
        $context = $this->authorizeTenant('category.view');

        $templates = CategoryTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $context->tenant->id)
            ->with('privileges.privilege')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CategoryTemplate $template): array => $this->mapTemplate($template))
            ->values()
            ->all();

        return Inertia::render('tenant/categories/Index', [
            'tenantId' => (string) $context->tenant->id,
            'categories' => $templates,
            'canManage' => $this->permissions->hasTenantPermission($context, 'category.manage'),
        ]);
    }

    public function create(): Response
    {
        $context = $this->authorizeTenant('category.manage');

        return Inertia::render('tenant/categories/Form', [
            'tenantId' => (string) $context->tenant->id,
            'category' => null,
            'privilegeCatalog' => $this->privilegeCatalog((string) $context->tenant->id),
        ]);
    }

    public function edit(string $locale, string $category_id): Response|RedirectResponse
    {
        unset($locale);

        $context = $this->authorizeTenant('category.manage');
        $template = CategoryTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $context->tenant->id)
            ->with('privileges.privilege')
            ->findOrFail($category_id);

        $mapped = $this->mapTemplate($template);
        if ($mapped['locked']) {
            return redirect()
                ->route('tenant.categories.index')
                ->with('error', 'This category is linked to a published or live event and cannot be edited.');
        }

        return Inertia::render('tenant/categories/Form', [
            'tenantId' => (string) $context->tenant->id,
            'category' => $mapped,
            'privilegeCatalog' => $this->privilegeCatalog((string) $context->tenant->id),
        ]);
    }

    private function authorizeTenant(string $permission): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, $permission), 403);

        return $context;
    }

    /**
     * @return list<array{id:string,key:string,label:string,label_ar:string|null,effect:string,target_type:string|null,target_id:string|null}>
     */
    private function privilegeCatalog(string $tenantId): array
    {
        return Privilege::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (Privilege $privilege): array => [
                'id' => (string) $privilege->id,
                'key' => $privilege->key,
                'label' => $privilege->label,
                'label_ar' => $privilege->label_ar,
                'effect' => $privilege->effect,
                'target_type' => $privilege->target_type,
                'target_id' => $privilege->target_id,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapTemplate(CategoryTemplate $template): array
    {
        $locked = EventCategory::query()
            ->where('category_template_id', $template->id)
            ->whereHas('event', fn ($query) => $query->whereIn('status', CategoryLockStatus::values()))
            ->exists();

        return [
            'id' => (string) $template->id,
            'name' => $template->name,
            'name_ar' => $template->name_ar,
            'slug' => $template->slug,
            'color' => $template->color,
            'sort_order' => $template->sort_order,
            'locked' => $locked,
            'privileges' => $template->privileges
                ->filter(fn (CategoryTemplatePrivilege $link) => $link->privilege !== null)
                ->map(fn (CategoryTemplatePrivilege $link): array => [
                    'id' => (string) $link->id,
                    'privilege_id' => (string) $link->privilege_id,
                    'key' => $link->privilege->key,
                    'label' => $link->privilege->label,
                    'label_ar' => $link->privilege->label_ar,
                    'effect' => $link->effect ?: $link->privilege->effect,
                    'target_type' => $link->privilege->target_type,
                    'target_id' => $link->privilege->target_id,
                ])->values()->all(),
        ];
    }
}
