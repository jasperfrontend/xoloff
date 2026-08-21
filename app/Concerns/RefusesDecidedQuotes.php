<?php

namespace App\Concerns;

use App\Models\Quote;
use Illuminate\Http\RedirectResponse;

/**
 * A quote the customer has answered is finished, and stays as they answered it.
 *
 * This is not tidiness. M6 hashes the rendered document at the moment of
 * signing, as the evidence of what the signer actually saw (SPEC §9), and an
 * edit afterwards would leave that hash describing something that no longer
 * exists. The same reasoning covers a denial: the terms someone refused are
 * the terms they refused.
 *
 * The screens stop offering these actions, but the screens are not the
 * protection - this is. A stale tab, a bookmarked URL or a second window is
 * enough to reach any of them.
 */
trait RefusesDecidedQuotes
{
    protected function refuseIfDecided(Quote $quote): ?RedirectResponse
    {
        if (! $quote->hasBeenDecided()) {
            return null;
        }

        return back()->withErrors(['quote' => __(
            'Quote :id has been :status by the customer, so it can no longer be changed. Raise a new quote instead.',
            ['id' => $quote->getKey(), 'status' => strtolower($quote->status->label())],
        )]);
    }
}
