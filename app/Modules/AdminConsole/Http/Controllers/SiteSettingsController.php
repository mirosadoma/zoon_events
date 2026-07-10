<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class SiteSettingsController extends Controller
{
    public function edit(SiteSettingsRepository $settings): Response
    {
        Gate::authorize('platform.configuration.view');

        return Inertia::render('platform/SiteSettings', [
            'settings' => $settings->toPublicArray(),
            'canManage' => Gate::allows('platform.tenant.manage'),
        ]);
    }

    public function update(Request $request, SiteSettingsRepository $settings): RedirectResponse
    {
        Gate::authorize('platform.tenant.manage');

        $validated = $request->validate([
            'app_name_en' => ['required', 'string', 'max:160'],
            'app_name_ar' => ['required', 'string', 'max:160'],
            'support_email' => ['nullable', 'email', 'max:254'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'about_en' => ['nullable', 'string', 'max:10000'],
            'about_ar' => ['nullable', 'string', 'max:10000'],
            'maintenance_enabled' => ['boolean'],
            'maintenance_message_en' => ['nullable', 'string', 'max:10000'],
            'maintenance_message_ar' => ['nullable', 'string', 'max:10000'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico', 'max:512'],
        ]);

        $current = $settings->current();
        $attributes = collect($validated)->except(['logo', 'favicon'])->all();
        $attributes['maintenance_enabled'] = $request->boolean('maintenance_enabled');

        if ($request->hasFile('logo')) {
            if ($current->logo_path) {
                Storage::disk('public')->delete($current->logo_path);
            }

            $attributes['logo_path'] = $request->file('logo')->store('site/branding', 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($current->favicon_path) {
                Storage::disk('public')->delete($current->favicon_path);
            }

            $attributes['favicon_path'] = $request->file('favicon')->store('site/branding', 'public');
        }

        $settings->update($attributes);

        return back()->with('status', 'site-settings-updated');
    }
}
