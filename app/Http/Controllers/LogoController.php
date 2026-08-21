<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Support\Logo\FetchedLogo;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the stored logos to a browser, and to an email client.
 *
 * Public, and deliberately so: the customer's portal shows one and a quote
 * email links the other, and both are the same images Xolution already
 * publishes on their own website. Neither is how the PDF gets its logo - that
 * embeds the bytes, because Gotenberg renders in its own container and cannot
 * necessarily reach here.
 */
class LogoController extends Controller
{
    /**
     * For screens and anything that can draw a vector.
     */
    public function show(): Response
    {
        return $this->serve(AppSettings::current()->webLogo());
    }

    /**
     * For email, which needs a raster: Gmail strips an SVG and Outlook will
     * not draw one.
     */
    public function email(): Response
    {
        return $this->serve(AppSettings::current()->emailLogo());
    }

    private function serve(?FetchedLogo $logo): Response
    {
        abort_if($logo === null, 404);

        return response($logo->bytes, 200, [
            'Content-Type' => $logo->mime,
            // Cached hard, and keyed by the bytes rather than by a timestamp:
            // a logo that has been replaced gets a different tag and is
            // fetched again, while an unchanged one is never re-sent.
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.md5($logo->bytes).'"',
        ]);
    }
}
