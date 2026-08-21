<?php

namespace App\Support\Logo;

use RuntimeException;
use Throwable;

/**
 * The logo at that address could not be used.
 *
 * Carries a message meant to be shown beside the field rather than logged:
 * whoever pasted the address is looking at the screen and is the only person
 * who can fix it.
 */
class LogoUnavailable extends RuntimeException
{
    public static function insecure(): self
    {
        return new self(__('Use an https address. A logo fetched over plain http can be tampered with on the way here, and it ends up on a document you send to clients.'));
    }

    public static function unroutable(): self
    {
        return new self(__('That address does not point at a public web server, so there is nothing to fetch.'));
    }

    public static function redirected(): self
    {
        return new self(__('That address redirects somewhere else. Paste the address it ends up at.'));
    }

    public static function unreachable(Throwable $previous): self
    {
        return new self(
            __('That address could not be reached. Check it in a browser first.'),
            previous: $previous,
        );
    }

    public static function refused(int $status): self
    {
        return new self(__('That address answered with an error (:status).', ['status' => $status]));
    }

    public static function notAnImage(string $type): self
    {
        return new self(__('That address returned :type rather than an image. PNG, JPG, WebP and SVG all work.', [
            'type' => $type === '' ? __('no content type') : $type,
        ]));
    }

    public static function tooLarge(int $kilobytes): self
    {
        return new self(__('That image is larger than :max KB. A logo does not need to be.', ['max' => $kilobytes]));
    }
}
