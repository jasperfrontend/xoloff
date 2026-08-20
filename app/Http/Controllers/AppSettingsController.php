<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppSettingsRequest;
use App\Models\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Application-wide configuration, as opposed to the per-user screens under
 * /settings. For now that is the logo the quote template prints (SPEC §6);
 * the validity window arrives in M4 and the notification toggles in M7.
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
            ],
        ]);
    }

    public function update(AppSettingsRequest $request): RedirectResponse
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
