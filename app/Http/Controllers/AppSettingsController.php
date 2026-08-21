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
 * Configuration shared by everyone, as opposed to the per-user screens beside
 * it under /settings. The logo the quote template prints (SPEC §6) and
 * Xolution's own details printed alongside it (SPEC §7); the notification
 * toggles arrive in M7.
 *
 * The logo saves through its own route rather than with the rest, because
 * fetching it can fail for reasons that have nothing to do with the other
 * fields, and a KvK number should not be held hostage to a web server being
 * briefly down.
 */
class AppSettingsController extends Controller
{
    /**
     * Which field takes which kind of file.
     *
     * @var array<string, list<string>>
     */
    private const LOGO_FIELDS = [
        'vector' => RemoteLogo::VECTOR_TYPES,
        'raster' => RemoteLogo::RASTER_TYPES,
    ];

    /**
     * Below this a logo looks soft in an email, where it is drawn at roughly
     * 150 to 200 pixels wide on a display that may be doubling everything.
     */
    private const MINIMUM_RASTER_WIDTH = 300;

    public function __construct(private readonly RemoteLogo $remoteLogo) {}

    public function edit(): Response
    {
        $settings = AppSettings::current();

        return Inertia::render('settings/Application', [
            'settings' => [
                'logo_vector_url' => $settings->logo_vector_url,
                'logo_raster_url' => $settings->logo_raster_url,
                // The stored bytes, not the addresses they came from. A
                // preview of the remote image would show what is out there
                // rather than what this application actually holds, which is
                // the one thing this screen is for. Both are shown side by
                // side so a pair that has drifted apart is visible.
                'logo_vector_preview_url' => $settings->logo_vector_mime === null ? null : route('logo.show'),
                'logo_raster_preview_url' => $settings->logo_raster_mime === null ? null : route('logo.email'),
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
     * Fetches both logos now rather than when a quote is printed or sent.
     *
     * A wrong address is then a message under the field, in front of the
     * person who typed it, instead of a logo missing from a document a client
     * already has. An address left empty removes that logo, which is why there
     * is no separate delete to keep in step with this.
     *
     * Two of them, because no single file works everywhere: the portal and the
     * PDF want an SVG, and email clients mostly will not render one.
     */
    public function storeLogo(LogoUrlRequest $request): RedirectResponse
    {
        $settings = AppSettings::current();
        $errors = [];
        $warnings = [];

        foreach (self::LOGO_FIELDS as $side => $accepting) {
            $field = "logo_{$side}_url";
            $url = $request->string($field)->trim()->toString();

            if ($url === '') {
                $settings->forgetLogo($side);

                continue;
            }

            // Unchanged addresses are left alone, so saving one field does not
            // re-fetch the other and cannot fail on it.
            if ($url === $settings->{$field}) {
                continue;
            }

            try {
                $logo = $this->remoteLogo->fetch($url, $accepting);
            } catch (LogoUnavailable $exception) {
                $errors[$field] = $exception->getMessage();

                continue;
            }

            $settings->storeLogo($side, $logo, $url);

            if ($logo->width !== null && $logo->width < self::MINIMUM_RASTER_WIDTH) {
                $warnings[] = __('That logo is only :width pixels wide. Use at least :minimum so it does not look soft in an email.', [
                    'width' => $logo->width,
                    'minimum' => self::MINIMUM_RASTER_WIDTH,
                ]);
            }
        }

        if ($errors !== []) {
            // The addresses are handed back so the fields keep what was typed;
            // correcting a typo should not mean pasting both again.
            return back()
                ->withInput($request->only(array_keys($request->rules())))
                ->withErrors($errors);
        }

        Inertia::flash('toast', $warnings === []
            ? ['type' => 'success', 'message' => __('Logos updated.')]
            // Saved anyway. A logo that is smaller than ideal is still a logo,
            // and refusing it would be this application having an opinion
            // about someone else's artwork.
            : ['type' => 'warning', 'message' => implode(' ', $warnings)]);

        return to_route('app-settings.edit');
    }
}
