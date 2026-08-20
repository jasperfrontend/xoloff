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
        return new self(__('The PDF service refused the request (:status).', ['status' => $status]));
    }
}
