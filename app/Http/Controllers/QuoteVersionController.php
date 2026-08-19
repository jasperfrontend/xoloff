<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\SaveQuoteVersion;
use App\Http\Requests\QuoteRequest;
use App\Models\Quote;
use App\Models\QuoteVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuoteVersionController extends Controller
{
    public function __construct(private readonly SaveQuoteVersion $saveQuoteVersion) {}

    /**
     * "Save as new version". This is the only thing that ever adds a row to
     * quote_versions after the first, which is what keeps the history free of
     * unfinished draft edits (SPEC §3).
     */
    public function store(QuoteRequest $request, Quote $quote): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $quote): void {
            $quote->update(['customer_id' => $data['customer_id']]);

            // Taken from the database rather than from what the page was
            // showing, so two saves racing cannot both claim the same number.
            // The lock is on the quote row, because Postgres refuses FOR UPDATE
            // alongside an aggregate. The unique index on
            // (quote_id, version_number) is the backstop.
            Quote::query()->whereKey($quote->getKey())->lockForUpdate()->first();

            $nextVersionNumber = (int) $quote->versions()->max('version_number') + 1;

            $this->saveQuoteVersion->handle(
                new QuoteVersion([
                    'quote_id' => $quote->id,
                    'version_number' => $nextVersionNumber,
                ]),
                $data,
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('New version saved.')]);

        return to_route('quotes.edit', $quote);
    }
}
