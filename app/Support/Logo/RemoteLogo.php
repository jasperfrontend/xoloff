<?php

namespace App\Support\Logo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches the logo from the address Xolution hosts it at.
 *
 * Called when the address is saved, never while a PDF is being rendered. That
 * is the whole point of storing the bytes: a wrong address is a message under
 * a field, and printing a quote depends on nobody else's web server.
 */
class RemoteLogo
{
    /**
     * Generous for a logo and small enough to be harmless. The old upload
     * capped at the same size.
     */
    private const MAX_KILOBYTES = 2048;

    /**
     * SVG is accepted although the upload this replaced refused it.
     *
     * An uploaded SVG was a real risk: it is a document that can carry script,
     * and it was going to be rendered by a Chromium. This one only ever
     * reaches an img element or a data uri in an img's src, where every
     * browser and Chromium alike treat SVG as an image and run nothing in it.
     * It must never be inlined into the page itself.
     *
     * @var list<string>
     */
    public const VECTOR_TYPES = ['image/svg+xml'];

    /**
     * What email can actually draw. Gmail strips an SVG and Outlook will not
     * render one, so the message needs a raster or it needs no image at all.
     *
     * @var list<string>
     */
    public const RASTER_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @param  list<string>  $accepting  the types this particular field takes
     *
     * @throws LogoUnavailable
     */
    public function fetch(string $url, array $accepting): FetchedLogo
    {
        $this->refuseAnythingButAPublicHttpsAddress($url);

        try {
            // Redirects are not followed on purpose. Whatever this validated a
            // moment ago is not necessarily where a redirect leads, and
            // re-checking every hop is a great deal of machinery for an
            // address someone can simply paste in its final form.
            $response = Http::withoutRedirecting()
                ->timeout(10)
                ->get($url);
        } catch (ConnectionException $exception) {
            throw LogoUnavailable::unreachable($exception);
        }

        if ($response->redirect()) {
            throw LogoUnavailable::redirected();
        }

        if ($response->failed()) {
            throw LogoUnavailable::refused($response->status());
        }

        // Only the type itself: a header may carry "image/svg+xml; charset=utf-8".
        $mime = trim(explode(';', $response->header('Content-Type'))[0]);

        $mime = strtolower($mime);

        if (! in_array($mime, $accepting, true)) {
            throw LogoUnavailable::wrongKind($mime, $accepting);
        }

        $bytes = $response->body();

        if (strlen($bytes) > self::MAX_KILOBYTES * 1024) {
            throw LogoUnavailable::tooLarge(self::MAX_KILOBYTES);
        }

        return new FetchedLogo($mime, $bytes, $this->widthOf($mime, $bytes));
    }

    /**
     * How wide a raster is, so the screen can say when one is too small to
     * look sharp. An SVG has no pixel width, which is the point of it.
     */
    private function widthOf(string $mime, string $bytes): ?int
    {
        if (in_array($mime, self::VECTOR_TYPES, true)) {
            return null;
        }

        $size = @getimagesizefromstring($bytes);

        return is_array($size) ? $size[0] : null;
    }

    /**
     * This application fetches an address a person typed, which is the shape
     * of a server-side request forgery whether or not anyone means it that
     * way. Both users are trusted, but the host this runs on can reach its
     * own cloud metadata service, and that is worth three lines to refuse.
     *
     * @throws LogoUnavailable
     */
    private function refuseAnythingButAPublicHttpsAddress(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($host) || $host === '') {
            throw LogoUnavailable::insecure();
        }

        // A literal address is checked as it stands; a name is checked against
        // what it resolves to.
        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolver->resolve($host);

        if ($addresses === []) {
            throw LogoUnavailable::unroutable();
        }

        foreach ($addresses as $address) {
            $isPublic = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($isPublic === false) {
                throw LogoUnavailable::unroutable();
            }
        }
    }
}
