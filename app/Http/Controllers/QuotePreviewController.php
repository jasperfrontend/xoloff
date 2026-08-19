<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuotePreviewRequest;
use App\Models\QuoteLineItem;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Support\Quotes\QuoteCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Live totals for the builder, calculated but never saved.
 *
 * The builder needs to show a total while lines are still being edited, and the
 * only safe way to do that is to ask the real engine. Reimplementing SPEC §5 in
 * TypeScript would give the browser and the server two separate opinions about
 * the money, and they would eventually disagree.
 */
class QuotePreviewController extends Controller
{
    public function __invoke(QuotePreviewRequest $request, QuoteCalculator $calculator): JsonResponse
    {
        $data = $request->validated();

        /** @var array<int, array<string, mixed>> $submitted */
        $submitted = $data['line_items'] ?? [];

        $version = new QuoteVersion([
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? null,
            'rounding_override' => $data['rounding_override'] ?? null,
        ]);

        $taxClasses = TaxClass::query()
            ->findMany(array_column($submitted, 'tax_class_id'))
            ->keyBy('id');

        $lineItems = new Collection(array_map(function (array $lineItem) use ($taxClasses): QuoteLineItem {
            $item = new QuoteLineItem([
                'name' => $lineItem['name'],
                'quantity' => $lineItem['quantity'],
                'unit_price_ex_vat' => $lineItem['unit_price_ex_vat'],
                'tax_class_id' => $lineItem['tax_class_id'],
                'discount_type' => $lineItem['discount_type'] ?? null,
                'discount_value' => $lineItem['discount_value'] ?? null,
            ]);

            // Set rather than loaded, so nothing here touches the database and
            // the engine never sees an unsaved model it would try to query.
            $item->setRelation('taxClass', $taxClasses[$lineItem['tax_class_id']]);

            return $item;
        }, $submitted));

        $version->setRelation('lineItems', $lineItems);

        return response()->json($calculator->calculate($version));
    }
}
