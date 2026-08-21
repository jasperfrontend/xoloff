<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Models\AppSettings;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Issues a quote to its customer (SPEC §7): a magic link, a date it stops
 * being valid, and a status saying it is out in the world.
 *
 * Delivering it is separate. The link this produces is what an email will
 * carry, and until Xolution's SMTP credentials arrive (SPEC §12) it is what
 * gets passed on by hand.
 */
final class SendQuote
{
    /**
     * Long enough that guessing is not a strategy. This is the customer's
     * whole credential for the quote - there is no second factor and no
     * account behind it - so it is sized like a password rather than like an
     * identifier.
     */
    private const TOKEN_LENGTH = 64;

    /**
     * @param  int|null  $validityDays  how long it stays valid, or null to follow the application default
     */
    public function handle(Quote $quote, ?int $validityDays = null): Quote
    {
        // Kept across a re-send rather than rotated. Both links lead to the
        // same place - the portal always shows the current version - so
        // rotating would only break the link in an email the customer already
        // has, for no gain.
        $quote->magic_link_token ??= Str::random(self::TOKEN_LENGTH);

        // Null means "follow the application default", so a window that
        // matches the default is stored as no override at all. Storing 30
        // where the default is 30 would silently detach this quote from a
        // later change to it.
        $quote->validity_days_override = $validityDays === AppSettings::current()->default_validity_days
            ? null
            : $validityDays;

        $quote->sent_at = CarbonImmutable::now();
        $quote->valid_until = CarbonImmutable::today()->addDays($quote->validityDays());

        // Back to sent even from opened. A re-send is a new offer to look at,
        // and whether the customer has opened this one is exactly what the
        // status is for.
        $quote->transitionTo(QuoteStatus::Sent, [
            'valid_until' => $quote->valid_until->toDateString(),
            'validity_days' => $quote->validityDays(),
        ]);

        return $quote;
    }
}
