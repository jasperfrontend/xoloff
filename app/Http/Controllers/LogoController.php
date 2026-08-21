<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the stored logo to a browser.
 *
 * Public, and deliberately so: the customer's portal shows it, and it is the
 * same image Xolution already publishes on their own website. What it is not
 * is how the PDF gets the logo - that embeds the bytes directly, because
 * Gotenberg renders in its own container and cannot necessarily reach here.
 */
class LogoController extends Controller
{
    public function __invoke(): Response
    {
        $logo = AppSettings::current()->logo();

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
