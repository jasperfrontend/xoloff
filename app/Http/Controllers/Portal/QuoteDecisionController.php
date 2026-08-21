<?php

namespace App\Http\Controllers\Portal;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\DenyQuoteRequest;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;

/**
 * The customer saying yes or no (SPEC §8).
 *
 * Both answers are final. A quote is not re-decided - it is superseded by a
 * new one, which is what versioning and re-sending are for. The guards here
 * are what makes that true rather than a convention, since a stale tab is all
 * it takes to submit twice.
 */
class QuoteDecisionController extends Controller
{
    public function approve(Quote $quote): RedirectResponse
    {
        if ($this->cannotDecide($quote)) {
            return $this->backToTheQuote($quote);
        }

        $quote->transitionTo(QuoteStatus::Approved);

        return $this->backToTheQuote($quote);
    }

    public function deny(DenyQuoteRequest $request, Quote $quote): RedirectResponse
    {
        if ($this->cannotDecide($quote)) {
            return $this->backToTheQuote($quote);
        }

        $reason = $request->string('reason')->trim()->toString();

        // Optional by design: SPEC §8 says denial opens a reason box, not that
        // it demands one. Someone who does not want to explain themselves must
        // still be able to decline.
        $quote->deny_reason = $reason === '' ? null : $reason;

        // Carried in the entry rather than left to the column, because the
        // reason is the substance of the event and the column is excluded from
        // automatic change logging for exactly that reason.
        $quote->transitionTo(QuoteStatus::Denied, ['deny_reason' => $quote->deny_reason]);

        return $this->backToTheQuote($quote);
    }

    /**
     * A decision needs a live link, something to decide on, and no decision
     * already made. Failing any of those is not an error to shout about: the
     * page they land back on says where things stand.
     */
    private function cannotDecide(Quote $quote): bool
    {
        return $quote->hasExpired()
            || $quote->hasBeenDecided()
            || $quote->currentVersion === null;
    }

    private function backToTheQuote(Quote $quote): RedirectResponse
    {
        return redirect()->route('portal.quote', $quote->magic_link_token);
    }
}
