<?php

namespace App\Support\Pdf;

use RuntimeException;
use Throwable;

/**
 * The PDF could not be produced.
 *
 * Carries a message meant to be shown rather than logged: whoever pressed
 * Download needs to know whether to wait, to fix something, or to give up.
 */
class PdfUnavailable extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(__('The PDF service is not configured yet, so quotes cannot be downloaded.'));
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self(
            __('The PDF service did not respond. It may be starting up, so try again in a moment.'),
            previous: $previous,
        );
    }

    public static function refused(int $status): self
    {
        // 401 and 403 have one cause worth naming. The container is behind
        // basic auth, and the credentials here drifting from the ones it was
        // started with is the way this breaks - a generic "refused (401)"
        // sends whoever hit it looking at the template instead.
        if (in_array($status, [401, 403], true)) {
            return new self(__('The PDF service refused our credentials. GOTENBERG_API_BASIC_AUTH_USERNAME and GOTENBERG_API_BASIC_AUTH_PASSWORD no longer match what the container was started with.'));
        }

        return new self(__('The PDF service refused the request (:status).', ['status' => $status]));
    }
}
