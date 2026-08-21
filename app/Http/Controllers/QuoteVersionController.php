<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\SaveQuoteVersion;
use App\Enums\AuditAction;
use App\Http\Requests\QuoteRequest;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Support\Audit\AuditLog;
use App\Support\Quotes\QuoteCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuoteVersionController extends Controller
{
    public function __construct(
        private readonly SaveQuoteVersion $saveQuoteVersion,
        private readonly QuoteCalculator $calculator,
    ) {}

    /**
     * The history of one quote (SPEC §6). Newest first, because the reason to
     * come here is almost always to compare against what is current.
     */
    public function index(Quote $quote): Response
    {
        $quote->load('customer:id,company_name');

        $versions = $quote->versions()
            ->with('lineItems.taxClass')
            ->orderByDesc('version_number')
            ->get();

        $currentVersionNumber = (int) $versions->max('version_number');

        return Inertia::render('quotes/versions/Index', [
            'quote' => [
                'id' => $quote->id,
                'customer_name' => $quote->customer->company_name,
            ],
            'versions' => $versions->map(fn (QuoteVersion $version): array => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'is_current' => $version->version_number === $currentVersionNumber,
                'saved_at' => $version->updated_at?->toIso8601String(),
                'line_count' => $version->lineItems->count(),
                'total' => $this->calculator->calculate($version)->total,
            ]),
        ]);
    }

    /**
     * A superseded version read-only, including the texts it was saved with.
     * There is no editing here: rewriting history is exactly what versioning
     * exists to prevent.
     */
    public function show(Quote $quote, QuoteVersion $version): Response
    {
        abort_unless($version->quote_id === $quote->id, 404);

        $version->load('lineItems.taxClass');

        return Inertia::render('quotes/versions/Show', [
            'quote' => [
                'id' => $quote->id,
                'customer_name' => $quote->customer->company_name,
            ],
            'version' => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'is_current' => $version->version_number === (int) $quote->versions()->max('version_number'),
                'saved_at' => $version->updated_at?->toIso8601String(),
                'intro_text_snapshot' => $version->intro_text_snapshot,
                'footer_text_snapshot' => $version->footer_text_snapshot,
                'line_items' => $version->lineItems->map(fn ($lineItem): array => [
                    'id' => $lineItem->id,
                    'name' => $lineItem->name,
                    'specs' => $lineItem->specs,
                    'quantity' => $lineItem->quantity,
                    'unit_price_ex_vat' => $lineItem->unit_price_ex_vat,
                    'tax_class_name' => $lineItem->taxClass->name,
                    'discount_type' => $lineItem->discount_type,
                    'discount_value' => $lineItem->discount_value,
                ])->all(),
            ],
            'totals' => $this->calculator->calculate($version),
        ]);
    }

    /**
     * Only a superseded version can be removed. The current one is the quote's
     * content, so deleting it would silently promote an older version into its
     * place - which reads as the quote changing by itself.
     */
    public function destroy(Quote $quote, QuoteVersion $version): RedirectResponse
    {
        abort_unless($version->quote_id === $quote->id, 404);

        if ($version->version_number === (int) $quote->versions()->max('version_number')) {
            return back()->withErrors([
                'version' => __('This is the current version of the quote, so it cannot be removed. Delete the whole quote instead.'),
            ]);
        }

        $context = $version->auditContext();

        $version->delete();

        AuditLog::record($version, AuditAction::Deleted, [
            ...$context,
            'attributes' => ['version_number' => $version->version_number],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version deleted.')]);

        return to_route('quotes.versions.index', $quote);
    }

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
                $quote,
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
