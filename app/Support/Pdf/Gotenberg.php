<?php

namespace App\Support\Pdf;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Turns an HTML document into a PDF using Xolution's existing Gotenberg
 * container (SPEC §1).
 *
 * Gotenberg drives a real Chromium, so the quote template is styled with
 * ordinary CSS rather than a PDF library's drawing primitives. That is the
 * whole reason SPEC §6 could drop the font and colour settings page: the
 * branded output was always going to be an HTML template underneath.
 */
class Gotenberg
{
    /**
     * The endpoint that takes an HTML document and gives back a PDF.
     */
    private const CONVERT_PATH = '/forms/chromium/convert/html';

    public function isConfigured(): bool
    {
        return is_string(config('services.gotenberg.url'))
            && config('services.gotenberg.url') !== '';
    }

    /**
     * @param  string  $document  the page itself, which Gotenberg requires to be named index.html
     * @param  string|null  $footer  markup repeated at the foot of every page, which is how page numbers get there
     * @param  array<string, string>  $margins  page margins keyed top, bottom, left, right, with units
     *
     * @throws PdfUnavailable
     */
    public function render(string $document, ?string $footer = null, array $margins = []): string
    {
        if (! $this->isConfigured()) {
            throw PdfUnavailable::notConfigured();
        }

        $request = Http::timeout((int) config('services.gotenberg.timeout', 30))
            ->asMultipart()
            ->attach('files', $document, 'index.html');

        if ($footer !== null) {
            $request = $request->attach('files', $footer, 'footer.html');
        }

        // Chromium's print API owns the page margins, so an @page rule in the
        // document is ignored. Sending them here is the only way the printed
        // page matches what the template was designed for.
        foreach ($margins as $edge => $size) {
            $request = $request->attach('margin'.ucfirst($edge), $size);
        }

        $username = config('services.gotenberg.username');
        $password = config('services.gotenberg.password');

        if (is_string($username) && $username !== '') {
            $request = $request->withBasicAuth($username, (string) $password);
        }

        try {
            $response = $request->post($this->endpoint());
        } catch (ConnectionException $exception) {
            throw PdfUnavailable::unreachable($exception);
        }

        if ($response->failed()) {
            throw PdfUnavailable::refused($response->status());
        }

        return $response->body();
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.gotenberg.url'), '/').self::CONVERT_PATH;
    }
}
