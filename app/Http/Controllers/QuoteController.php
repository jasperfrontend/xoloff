<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\SaveQuoteVersion;
use App\Http\Requests\QuoteRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Support\Quotes\QuoteCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function __construct(
        private readonly SaveQuoteVersion $saveQuoteVersion,
        private readonly QuoteCalculator $calculator,
    ) {}

    public function index(): Response
    {
        $quotes = Quote::query()
            ->with(['customer:id,company_name', 'currentVersion.lineItems.taxClass'])
            ->latest('id')
            ->get();

        return Inertia::render('quotes/Index', [
            'quotes' => $quotes->map(fn (Quote $quote): array => [
                'id' => $quote->id,
                'customer_name' => $quote->customer->company_name,
                'version_number' => $quote->currentVersion?->version_number,
                'line_count' => $quote->currentVersion?->lineItems->count() ?? 0,
                'total' => $quote->currentVersion === null
                    ? '0.00'
                    : $this->calculator->calculate($quote->currentVersion)->total,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('quotes/Create', $this->builderOptions());
    }

    public function store(QuoteRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $quote = DB::transaction(function () use ($data): Quote {
            $quote = Quote::create(['customer_id' => $data['customer_id']]);

            $this->saveQuoteVersion->handle(
                new QuoteVersion(['quote_id' => $quote->id, 'version_number' => 1]),
                $data,
            );

            return $quote;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote created.')]);

        return to_route('quotes.edit', $quote);
    }

    public function edit(Quote $quote): Response
    {
        // Fetched directly rather than through load(), so that a quote with no
        // versions at all stays visibly nullable here.
        $version = $quote->currentVersion()->with('lineItems.taxClass')->first();

        return Inertia::render('quotes/Edit', [
            'quote' => [
                'id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'version_number' => $version->version_number ?? 1,
                'version_count' => $quote->versions()->count(),
                'discount_type' => $version?->discount_type,
                'discount_value' => $version?->discount_value,
                'rounding_override' => $version?->rounding_override,
                'line_items' => $version === null ? [] : $version->lineItems->map(fn ($lineItem): array => [
                    'product_id' => $lineItem->product_id,
                    'name' => $lineItem->name,
                    'specs' => $lineItem->specs,
                    'quantity' => $lineItem->quantity,
                    'unit_price_ex_vat' => $lineItem->unit_price_ex_vat,
                    'tax_class_id' => $lineItem->tax_class_id,
                    'discount_type' => $lineItem->discount_type,
                    'discount_value' => $lineItem->discount_value,
                ])->all(),
            ],
            'totals' => $version === null ? null : $this->calculator->calculate($version),
            ...$this->builderOptions(),
        ]);
    }

    /**
     * Saves over the current version. A new version row is only ever created by
     * the explicit "Save as new version" action, never automatically, so that
     * the history is not flooded with unfinished draft edits (SPEC §3).
     */
    public function update(QuoteRequest $request, Quote $quote): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $quote): void {
            $quote->update(['customer_id' => $data['customer_id']]);

            $version = $quote->currentVersion
                ?? new QuoteVersion(['quote_id' => $quote->id, 'version_number' => 1]);

            $this->saveQuoteVersion->handle($version, $data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote saved.')]);

        return to_route('quotes.edit', $quote);
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $quote->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote deleted.')]);

        return to_route('quotes.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function builderOptions(): array
    {
        return [
            'customers' => Customer::query()
                ->orderBy('company_name')
                ->get(['id', 'company_name']),
            'products' => Product::query()
                ->with('specs:id,product_id,key,value')
                ->orderBy('name')
                ->get(['id', 'name', 'price_ex_vat', 'tax_class_id']),
            'taxClasses' => TaxClass::query()
                ->orderByDesc('percentage')
                ->get(['id', 'name', 'percentage']),
        ];
    }
}
