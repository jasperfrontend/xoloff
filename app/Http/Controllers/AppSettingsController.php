<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppSettingsLogoRequest;
use App\Http\Requests\AppSettingsRequest;
use App\Models\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Application-wide configuration, as opposed to the per-user screens under
 * /settings. The logo the quote template prints (SPEC §6) and Xolution's own
 * details printed alongside it (SPEC §7); the notification toggles arrive
 * in M7.
 *
 * The logo saves through its own route rather than with the rest. A file
 * input cannot be redisplayed with what was submitted, so bundling the two
 * would mean a validation error anywhere on the screen silently dropped the
 * chosen file.
 */
class AppSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = AppSettings::current();

        return Inertia::render('app-settings/Edit', [
            'settings' => [
                'logo_path' => $settings->logo_path,
                'logo_url' => $settings->logo_path === null
                    ? null
                    : Storage::disk('public')->url($settings->logo_path),
                'company_name' => $settings->company_name,
                'company_address' => $settings->company_address,
                'company_kvk' => $settings->company_kvk,
                'company_vat_number' => $settings->company_vat_number,
            ],
        ]);
    }

    public function update(AppSettingsRequest $request): RedirectResponse
    {
        AppSettings::current()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('app-settings.edit');
    }

    public function storeLogo(AppSettingsLogoRequest $request): RedirectResponse
    {
        $settings = AppSettings::current();
        $logo = $request->file('logo');

        // Validation has already required it; this narrows the type rather
        // than asserting it.
        if (! $logo instanceof UploadedFile) {
            return back();
        }

        // Stored before the old path is dropped, so a failed write cannot leave
        // the settings pointing at a file that no longer exists.
        $path = $logo->store('logos', 'public');

        $this->forget($settings->logo_path);

        $settings->update(['logo_path' => $path]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo uploaded.')]);

        return to_route('app-settings.edit');
    }

    public function destroyLogo(): RedirectResponse
    {
        $settings = AppSettings::current();

        $this->forget($settings->logo_path);

        $settings->update(['logo_path' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return to_route('app-settings.edit');
    }

    /**
     * Quotes never reference the logo file: the PDF embeds whatever the logo is
     * at the moment it is generated. So removing the old file leaves nothing
     * pointing at a gap.
     */
    private function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
