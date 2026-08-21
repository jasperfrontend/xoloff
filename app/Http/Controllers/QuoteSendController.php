<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\SendQuote;
use App\Concerns\RefusesDecidedQuotes;
use App\Http\Requests\SendQuoteRequest;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * "Send quote" (SPEC §7). Issues the magic link, sets the validity window and
 * moves the quote to sent.
 */
class QuoteSendController extends Controller
{
    use RefusesDecidedQuotes;

    public function __construct(private readonly SendQuote $sendQuote) {}

    public function __invoke(SendQuoteRequest $request, Quote $quote): RedirectResponse
    {
        if ($refusal = $this->refuseIfDecided($quote)) {
            return $refusal;
        }

        // A quote with nothing saved on it has no content to offer, and a
        // magic link leading to an empty page is worse than a button that
        // declines. Same guard as the PDF, for the same reason.
        if ($quote->currentVersion === null) {
            return back()->withErrors(['send' => __('This quote has no saved version to send yet.')]);
        }

        $delivered = $this->sendQuote->handle($quote, $request->integer('validity_days') ?: null);

        // The quote is sent either way: the link is live and the status has
        // moved. What failed is the carrying of it, and saying so beats a
        // success message that leaves someone waiting for a reply to a message
        // the customer never received - the link is on the screen behind this,
        // ready to pass on by hand.
        if (! $delivered) {
            return to_route('quotes.edit', $quote)->withErrors([
                'send' => __('The quote is marked as sent and its link works, but the email could not be delivered to :email. Send them the link below yourself.', [
                    'email' => $quote->customer->email,
                ]),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Quote sent to :email.', ['email' => $quote->customer->email]),
        ]);

        return to_route('quotes.edit', $quote);
    }
}
