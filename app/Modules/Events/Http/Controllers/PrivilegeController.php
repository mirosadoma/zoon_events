<?php

namespace App\Modules\Events\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplatePrivilege;
use App\Modules\Events\Infrastructure\Persistence\Models\Privilege;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PrivilegeController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
    ) {}

    public function index()
    {
        $context = $this->contextStore->current();

        $privileges = Privilege::query()
            ->where('tenant_id', $context->tenant->id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (Privilege $privilege) => $this->mapPrivilege($privilege));

        return $this->success($privileges->all());
    }

    public function store(Request $request)
    {
        $context = $this->contextStore->current();
        $validated = $this->validated($request, $context->tenant->id);

        $privilege = Privilege::query()->create([
            'tenant_id' => $context->tenant->id,
            'key' => $validated['key'],
            'label' => $validated['label'],
            'label_ar' => $validated['label_ar'] ?? null,
            'effect' => $validated['effect'],
            'target_type' => $validated['target_type'] ?? null,
            'target_id' => $validated['target_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return $this->success($this->mapPrivilege($privilege), 201);
    }

    public function show(string $privilege_id)
    {
        $context = $this->contextStore->current();
        $privilege = Privilege::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($privilege_id);

        return $this->success($this->mapPrivilege($privilege));
    }

    public function update(Request $request, string $privilege_id)
    {
        $context = $this->contextStore->current();
        $privilege = Privilege::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($privilege_id);

        $validated = $this->validated($request, $context->tenant->id, $privilege->id);
        $privilege->fill($validated)->save();

        return $this->success($this->mapPrivilege($privilege->refresh()));
    }

    public function destroy(string $privilege_id)
    {
        $context = $this->contextStore->current();
        $privilege = Privilege::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($privilege_id);

        $inUse = CategoryTemplatePrivilege::query()
            ->where('privilege_id', $privilege->id)
            ->exists();

        if ($inUse) {
            throw FoundationException::conflict(
                'privilege_in_use',
                'This privilege is assigned to one or more categories and cannot be deleted.',
            );
        }

        $privilege->delete();

        return $this->empty();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int|string $tenantId, int|string|null $ignoreId = null): array
    {
        $data = $request->validate([
            'key' => [
                $ignoreId === null ? 'required' : 'sometimes',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('privileges', 'key')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($ignoreId),
            ],
            'label' => [$ignoreId === null ? 'required' : 'sometimes', 'string', 'max:150'],
            'label_ar' => ['nullable', 'string', 'max:150'],
            'effect' => [$ignoreId === null ? 'required' : 'sometimes', Rule::in(['allow', 'deny'])],
            'target_type' => ['nullable', Rule::in(['gate', 'zone', 'parking', 'lounge', 'other'])],
            'target_id' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        if (isset($data['key'])) {
            $data['key'] = Str::snake(Str::lower($data['key']));
        }

        return $data;
    }

    private function mapPrivilege(Privilege $privilege): array
    {
        return [
            'id' => (string) $privilege->id,
            'key' => $privilege->key,
            'label' => $privilege->label,
            'label_ar' => $privilege->label_ar,
            'effect' => $privilege->effect,
            'target_type' => $privilege->target_type,
            'target_id' => $privilege->target_id,
            'sort_order' => $privilege->sort_order,
            'in_use' => CategoryTemplatePrivilege::query()
                ->where('privilege_id', $privilege->id)
                ->exists(),
        ];
    }
}
