<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\SendQuote;
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
    public function __construct(private readonly SendQuote $sendQuote) {}

    public function __invoke(SendQuoteRequest $request, Quote $quote): RedirectResponse
    {
        // A quote with nothing saved on it has no content to offer, and a
        // magic link leading to an empty page is worse than a button that
        // declines. Same guard as the PDF, for the same reason.
        if ($quote->currentVersion === null) {
            return back()->withErrors(['send' => __('This quote has no saved version to send yet.')]);
        }

        $this->sendQuote->handle($quote, $request->integer('validity_days') ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote sent.')]);

        return to_route('quotes.edit', $quote);
    }
}
