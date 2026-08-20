<?php

namespace App\Actions\Quotes;

use App\Enums\PremadeTextKey;
use App\Models\PremadeText;
use App\Models\QuoteVersion;

/**
 * Writes submitted builder content onto a quote version. Shared by saving the
 * current version in place and by saving as a new version, so the two can never
 * disagree about what a version is made of.
 */
final class SaveQuoteVersion
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(QuoteVersion $version, array $data): QuoteVersion
    {
        $version->fill([
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? null,
            'rounding_override' => $data['rounding_override'] ?? null,

            // Copied at save time rather than referenced live (SPEC §3).
            // Taken on every save, including saving over the current version,
            // because "save time" is what the snapshot is a snapshot of. What
            // this protects is the versions behind it: once superseded, a
            // version is never written to again, so the wording a customer saw
            // or signed survives any later edit to the global texts.
            'intro_text_snapshot' => PremadeText::contentFor(PremadeTextKey::Intro),
            'footer_text_snapshot' => PremadeText::contentFor(PremadeTextKey::Footer),
        ])->save();

        // Lines are replaced wholesale rather than diffed, matching how product
        // specs are handled: the list is short, order matters, and rewriting it
        // avoids stale rows.
        $version->lineItems()->delete();

        /** @var array<int, array<string, mixed>> $lineItems */
        $lineItems = $data['line_items'] ?? [];

        foreach ($lineItems as $lineItem) {
            $version->lineItems()->create([
                'product_id' => $lineItem['product_id'] ?? null,
                'name' => $lineItem['name'],
                'specs' => $lineItem['specs'] ?? null,
                'quantity' => $lineItem['quantity'],
                'unit_price_ex_vat' => $lineItem['unit_price_ex_vat'],
                'tax_class_id' => $lineItem['tax_class_id'],
                'discount_type' => $lineItem['discount_type'] ?? null,
                'discount_value' => $lineItem['discount_value'] ?? null,
            ]);
        }

        return $version->load('lineItems.taxClass');
    }
}
