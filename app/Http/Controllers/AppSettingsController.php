<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppSettingsRequest;
use App\Http\Requests\LogoUrlRequest;
use App\Models\AppSettings;
use App\Support\Logo\LogoUnavailable;
use App\Support\Logo\RemoteLogo;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Application-wide configuration, as opposed to the per-user screens under
 * /settings. The logo the quote template prints (SPEC §6) and Xolution's own
 * details printed alongside it (SPEC §7); the notification toggles arrive
 * in M7.
 *
 * The logo saves through its own route rather than with the rest, because
 * fetching it can fail for reasons that have nothing to do with the other
 * fields, and a KvK number should not be held hostage to a web server being
 * briefly down.
 */
class AppSettingsController extends Controller
{
    public function __construct(private readonly RemoteLogo $remoteLogo) {}

    public function edit(): Response
    {
        $settings = AppSettings::current();

        return Inertia::render('app-settings/Edit', [
            'settings' => [
                'logo_url' => $settings->logo_url,
                // The stored bytes, not the address they came from. A preview
                // of the remote image would show what is out there rather than
                // what will actually print, which is the one thing this screen
                // is for.
                'logo_preview_url' => $settings->hasLogo() ? route('logo.show') : null,
                'company_name' => $settings->company_name,
                'company_address' => $settings->company_address,
                'company_kvk' => $settings->company_kvk,
                'company_vat_number' => $settings->company_vat_number,
                'default_validity_days' => $settings->default_validity_days,
            ],
        ]);
    }

    public function update(AppSettingsRequest $request): RedirectResponse
    {
        AppSettings::current()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('app-settings.edit');
    }

    /**
     * Fetches the logo now rather than when a quote is printed.
     *
     * A wrong address is then a message under the field, in front of the
     * person who typed it, instead of a logo missing from a document a client
     * already has.
     */
    public function storeLogo(LogoUrlRequest $request): RedirectResponse
    {
        $url = $request->string('logo_url')->trim()->toString();

        try {
            $logo = $this->remoteLogo->fetch($url);
        } catch (LogoUnavailable $exception) {
            // The address is handed back so the field keeps what was typed;
            // correcting a typo should not mean pasting the whole thing again.
            return back()
                ->withInput(['logo_url' => $url])
                ->withErrors(['logo_url' => $exception->getMessage()]);
        }

        AppSettings::current()->storeLogo($logo, $url);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return to_route('app-settings.edit');
    }

    public function destroyLogo(): RedirectResponse
    {
        AppSettings::current()->forgetLogo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return to_route('app-settings.edit');
    }
}
