<?php

namespace App\Modules\Events\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Events\Domain\CategoryLockStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplatePrivilege;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\Privilege;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryTemplateController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
    ) {}

    public function index()
    {
        $context = $this->contextStore->current();

        $templates = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->with('privileges.privilege')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CategoryTemplate $template) => $this->mapTemplate($template));

        return $this->success($templates->all());
    }

    public function store(Request $request)
    {
        $context = $this->contextStore->current();
        $validated = $this->validated($request, $context->tenant->id);

        $template = DB::transaction(function () use ($context, $validated): CategoryTemplate {
            $template = CategoryTemplate::query()->create([
                'tenant_id' => $context->tenant->id,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'slug' => $this->uniqueSlug($context->tenant->id, $validated['name']),
                'color' => $validated['color'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            $this->syncPrivileges($template, $context->tenant->id, $validated['privileges'] ?? []);

            return $template;
        });

        return $this->success($this->mapTemplate($template->load('privileges.privilege')), 201);
    }

    public function show(string $template_id)
    {
        $context = $this->contextStore->current();
        $template = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->with('privileges.privilege')
            ->findOrFail($template_id);

        return $this->success($this->mapTemplate($template));
    }

    public function update(Request $request, string $template_id)
    {
        $context = $this->contextStore->current();
        $template = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($template_id);

        $this->assertUnlocked($template);

        $validated = $this->validated($request, $context->tenant->id, updating: true);

        DB::transaction(function () use ($template, $validated, $context): void {
            $fields = collect($validated)->except('privileges')->all();

            if (isset($fields['name']) && is_string($fields['name'])) {
                $fields['slug'] = $this->uniqueSlug($context->tenant->id, $fields['name'], $template->id);
            }

            $template->fill($fields)->save();

            if (array_key_exists('privileges', $validated)) {
                $this->syncPrivileges($template, $context->tenant->id, $validated['privileges'] ?? []);
            }
        });

        return $this->success($this->mapTemplate($template->refresh()->load('privileges.privilege')));
    }

    public function destroy(string $template_id)
    {
        $context = $this->contextStore->current();
        $template = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($template_id);

        $this->assertUnlocked($template);
        $template->delete();

        return $this->empty();
    }

    private function assertUnlocked(CategoryTemplate $template): void
    {
        $locked = EventCategory::query()
            ->where('category_template_id', $template->id)
            ->whereHas('event', fn ($query) => $query->whereIn('status', CategoryLockStatus::values()))
            ->exists();

        if ($locked) {
            throw FoundationException::conflict(
                'category_locked',
                'This category is linked to a published or live event and cannot be edited or deleted.',
            );
        }
    }

    /**
     * @param  list<array{privilege_id:int|string,effect?:string}>  $privileges
     */
    private function syncPrivileges(CategoryTemplate $template, int|string $tenantId, array $privileges): void
    {
        $template->privileges()->delete();

        foreach ($privileges as $row) {
            $privilege = Privilege::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($row['privilege_id']);

            $template->privileges()->create([
                'privilege_id' => $privilege->id,
                'effect' => $row['effect'] ?? $privilege->effect,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int|string $tenantId, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['integer', 'min:0'],
            'privileges' => ['nullable', 'array'],
            'privileges.*.privilege_id' => [
                'required',
                'integer',
                Rule::exists('privileges', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'privileges.*.effect' => ['nullable', Rule::in(['allow', 'deny'])],
        ]);
    }

    private function uniqueSlug(int|string $tenantId, string $name, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 1;

        while (
            CategoryTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

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
                ->map(fn (CategoryTemplatePrivilege $link) => [
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
