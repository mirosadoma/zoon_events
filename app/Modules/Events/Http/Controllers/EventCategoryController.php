<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategoryPrivilege;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventCategoryController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
    ) {}

    public function index(string $event_id)
    {
        $categories = EventCategory::query()
            ->where('event_id', $event_id)
            ->with('privileges')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EventCategory $c) => $this->mapCategory($c));

        return $this->success($categories->all());
    }

    public function store(Request $request, string $event_id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['integer', 'min:0'],
            'category_template_id' => ['nullable', 'exists:category_templates,id'],
            'privileges' => ['nullable', 'array'],
            'privileges.*.key' => ['required', 'string', 'max:80'],
            'privileges.*.label' => ['required', 'string', 'max:150'],
            'privileges.*.label_ar' => ['nullable', 'string', 'max:150'],
            'privileges.*.effect' => ['required', 'in:allow,deny'],
            'privileges.*.target_type' => ['nullable', 'string', 'max:50'],
            'privileges.*.target_id' => ['nullable', 'string', 'max:100'],
        ]);

        $category = DB::transaction(function () use ($event_id, $validated): EventCategory {
            $baseSlug = Str::slug($validated['name']) ?: 'category';
            $slug = $baseSlug;
            $suffix = 1;

            while (
                EventCategory::query()
                    ->where('event_id', $event_id)
                    ->where('slug', $slug)
                    ->exists()
            ) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $category = EventCategory::query()->create([
                'event_id' => $event_id,
                'category_template_id' => $validated['category_template_id'] ?? null,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'slug' => $slug,
                'color' => $validated['color'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if (! empty($validated['privileges'])) {
                foreach ($validated['privileges'] as $priv) {
                    $category->privileges()->create($priv);
                }
            }

            return $category;
        });

        return $this->success($this->mapCategory($category->load('privileges')), 201);
    }

    /** Apply tenant category templates to an event */
    public function applyTemplates(Request $request, string $event_id)
    {
        $context = $this->contextStore->current();

        $templates = CategoryTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->with('privileges')
            ->orderBy('sort_order')
            ->get();

        if ($templates->isEmpty()) {
            return $this->success([], 200);
        }

        $existingSlugs = EventCategory::query()
            ->where('event_id', $event_id)
            ->pluck('slug')
            ->all();

        $existingTemplateIds = EventCategory::query()
            ->where('event_id', $event_id)
            ->whereNotNull('category_template_id')
            ->pluck('category_template_id')
            ->all();

        $created = DB::transaction(function () use ($event_id, $templates, $existingSlugs, $existingTemplateIds): array {
            $results = [];

            foreach ($templates as $template) {
                if (in_array($template->id, $existingTemplateIds, true) || in_array($template->slug, $existingSlugs, true)) {
                    continue;
                }

                $category = EventCategory::query()->create([
                    'event_id' => $event_id,
                    'category_template_id' => $template->id,
                    'name' => $template->name,
                    'name_ar' => $template->name_ar,
                    'slug' => $template->slug,
                    'color' => $template->color,
                    'capacity' => null,
                    'sort_order' => $template->sort_order,
                ]);

                foreach ($template->privileges as $priv) {
                    $category->privileges()->create([
                        'key' => $priv->key,
                        'label' => $priv->label,
                        'label_ar' => $priv->label_ar,
                        'effect' => $priv->effect,
                        'target_type' => $priv->target_type,
                        'target_id' => $priv->target_id,
                    ]);
                }

                $existingSlugs[] = $template->slug;
                $results[] = $category->load('privileges');
            }

            return $results;
        });

        return $this->success(
            array_map(fn (EventCategory $c) => $this->mapCategory($c), $created),
            $created === [] ? 200 : 201,
        );
    }

    public function update(Request $request, string $event_id, string $category_id)
    {
        $category = EventCategory::query()
            ->where('event_id', $event_id)
            ->findOrFail($category_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['integer', 'min:0'],
            'privileges' => ['nullable', 'array'],
            'privileges.*.key' => ['required', 'string', 'max:80'],
            'privileges.*.label' => ['required', 'string', 'max:150'],
            'privileges.*.label_ar' => ['nullable', 'string', 'max:150'],
            'privileges.*.effect' => ['required', 'in:allow,deny'],
            'privileges.*.target_type' => ['nullable', 'string', 'max:50'],
            'privileges.*.target_id' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($category, $validated): void {
            $fields = collect($validated)->except('privileges')->all();

            if (array_key_exists('name', $fields) && is_string($fields['name'])) {
                $baseSlug = Str::slug($fields['name']) ?: 'category';
                $slug = $baseSlug;
                $suffix = 1;

                while (
                    EventCategory::query()
                        ->where('event_id', $category->event_id)
                        ->where('slug', $slug)
                        ->where('id', '!=', $category->id)
                        ->exists()
                ) {
                    $slug = "{$baseSlug}-{$suffix}";
                    $suffix++;
                }

                $fields['slug'] = $slug;
            }

            $category->fill($fields)->save();

            if (array_key_exists('privileges', $validated)) {
                $category->privileges()->delete();
                foreach ($validated['privileges'] ?? [] as $priv) {
                    $category->privileges()->create($priv);
                }
            }
        });

        return $this->success($this->mapCategory($category->refresh()->load('privileges')));
    }

    public function destroy(string $event_id, string $category_id)
    {
        $category = EventCategory::query()
            ->where('event_id', $event_id)
            ->findOrFail($category_id);

        $category->delete();

        return $this->empty();
    }

    private function mapCategory(EventCategory $c): array
    {
        return [
            'id' => (string) $c->id,
            'event_id' => (string) $c->event_id,
            'category_template_id' => $c->category_template_id !== null ? (string) $c->category_template_id : null,
            'name' => $c->name,
            'name_ar' => $c->name_ar,
            'slug' => $c->slug,
            'color' => $c->color,
            'capacity' => $c->capacity,
            'sort_order' => $c->sort_order,
            'privileges' => $c->privileges->map(fn (EventCategoryPrivilege $p) => [
                'id' => (string) $p->id,
                'key' => $p->key,
                'label' => $p->label,
                'label_ar' => $p->label_ar,
                'effect' => $p->effect,
                'target_type' => $p->target_type,
                'target_id' => $p->target_id,
            ])->values()->all(),
        ];
    }
}
