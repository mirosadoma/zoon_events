<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplatePrivilege;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            ->with('privileges')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CategoryTemplate $t) => $this->mapTemplate($t));

        return $this->success($templates->all());
    }

    public function store(Request $request)
    {
        $context = $this->contextStore->current();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['integer', 'min:0'],
            'privileges' => ['nullable', 'array'],
            'privileges.*.key' => ['required', 'string', 'max:80'],
            'privileges.*.label' => ['required', 'string', 'max:150'],
            'privileges.*.label_ar' => ['nullable', 'string', 'max:150'],
            'privileges.*.effect' => ['required', 'in:allow,deny'],
            'privileges.*.target_type' => ['nullable', 'string', 'max:50'],
            'privileges.*.target_id' => ['nullable', 'string', 'max:100'],
        ]);

        $template = DB::transaction(function () use ($context, $validated): CategoryTemplate {
            $template = CategoryTemplate::query()->create([
                'tenant_id' => $context->tenant->id,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'slug' => Str::slug($validated['name']),
                'color' => $validated['color'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if (! empty($validated['privileges'])) {
                foreach ($validated['privileges'] as $priv) {
                    $template->privileges()->create($priv);
                }
            }

            return $template;
        });

        return $this->success($this->mapTemplate($template->load('privileges')), 201);
    }

    public function show(string $template_id)
    {
        $context = $this->contextStore->current();
        $template = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->with('privileges')
            ->findOrFail($template_id);

        return $this->success($this->mapTemplate($template));
    }

    public function update(Request $request, string $template_id)
    {
        $context = $this->contextStore->current();
        $template = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($template_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['integer', 'min:0'],
            'privileges' => ['nullable', 'array'],
            'privileges.*.key' => ['required', 'string', 'max:80'],
            'privileges.*.label' => ['required', 'string', 'max:150'],
            'privileges.*.label_ar' => ['nullable', 'string', 'max:150'],
            'privileges.*.effect' => ['required', 'in:allow,deny'],
            'privileges.*.target_type' => ['nullable', 'string', 'max:50'],
            'privileges.*.target_id' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($template, $validated): void {
            $template->fill(collect($validated)->except('privileges')->all())->save();

            if (array_key_exists('privileges', $validated)) {
                $template->privileges()->delete();
                foreach ($validated['privileges'] ?? [] as $priv) {
                    $template->privileges()->create($priv);
                }
            }
        });

        return $this->success($this->mapTemplate($template->refresh()->load('privileges')));
    }

    public function destroy(string $template_id)
    {
        $context = $this->contextStore->current();
        $template = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($template_id);

        $template->delete();

        return $this->empty();
    }

    private function mapTemplate(CategoryTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'name_ar' => $t->name_ar,
            'slug' => $t->slug,
            'color' => $t->color,
            'sort_order' => $t->sort_order,
            'privileges' => $t->privileges->map(fn (CategoryTemplatePrivilege $p) => [
                'id' => $p->id,
                'key' => $p->key,
                'label' => $p->label,
                'label_ar' => $p->label_ar,
                'effect' => $p->effect,
                'target_type' => $p->target_type,
                'target_id' => $p->target_id,
            ])->all(),
        ];
    }
}
