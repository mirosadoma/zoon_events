<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class GeographyAdminController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('platform.configuration.view');

        $countries = Country::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name_en')])
            ->orderBy('name_en')
            ->get()
            ->map(fn (Country $country): array => [
                'id' => (string) $country->id,
                'code' => $country->code,
                'name_en' => $country->name_en,
                'name_ar' => $country->name_ar,
                'is_active' => $country->is_active,
                'cities' => $country->cities->map(fn (City $city): array => [
                    'id' => (string) $city->id,
                    'name_en' => $city->name_en,
                    'name_ar' => $city->name_ar,
                    'is_active' => $city->is_active,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('platform/Geography', [
            'countries' => $countries,
        ]);
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        Gate::authorize('platform.tenant.manage');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:3', 'unique:countries,code'],
            'name_en' => ['required', 'string', 'max:160'],
            'name_ar' => ['required', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Country::query()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('status', 'country-created');
    }

    public function updateCountry(Request $request): RedirectResponse
    {
        Gate::authorize('platform.tenant.manage');

        $countryId = $this->resolveRouteId('country');
        $model = Country::query()->findOrFail($countryId);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:3', 'unique:countries,code,'.$model->id],
            'name_en' => ['required', 'string', 'max:160'],
            'name_ar' => ['required', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $model->update([
            ...$data,
            'is_active' => $data['is_active'] ?? false,
        ]);

        return back()->with('status', 'country-updated');
    }

    public function destroyCountry(): RedirectResponse
    {
        Gate::authorize('platform.tenant.manage');

        $countryId = $this->resolveRouteId('country');
        $model = Country::query()->findOrFail($countryId);

        $inUse = EventVenue::query()->where('country_id', $model->id)->exists();

        if ($inUse) {
            return back()->withErrors(['country' => 'Country is linked to event venues and cannot be deleted.']);
        }

        $model->delete();

        return back()->with('status', 'country-deleted');
    }

    public function storeCity(Request $request): RedirectResponse
    {
        Gate::authorize('platform.tenant.manage');

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name_en' => ['required', 'string', 'max:160'],
            'name_ar' => ['required', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        City::query()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with('status', 'city-created');
    }

    public function updateCity(Request $request): RedirectResponse
    {
        Gate::authorize('platform.tenant.manage');

        $cityId = $this->resolveRouteId('city');
        $model = City::query()->findOrFail($cityId);

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name_en' => ['required', 'string', 'max:160'],
            'name_ar' => ['required', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $model->update([
            ...$data,
            'is_active' => $data['is_active'] ?? false,
        ]);

        return back()->with('status', 'city-updated');
    }

    public function destroyCity(): RedirectResponse
    {
        Gate::authorize('platform.tenant.manage');

        $cityId = $this->resolveRouteId('city');
        $model = City::query()->findOrFail($cityId);

        $inUse = EventVenue::query()->where('city_id', $model->id)->exists();

        if ($inUse) {
            return back()->withErrors(['city' => 'City is linked to event venues and cannot be deleted.']);
        }

        $model->delete();

        return back()->with('status', 'city-deleted');
    }

    private function resolveRouteId(string $parameter): string
    {
        $resolved = request()->route($parameter);

        abort_if(! is_string($resolved) || $resolved === '', 404);

        return $resolved;
    }
}
