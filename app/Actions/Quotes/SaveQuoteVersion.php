<?php

namespace App\Actions\Quotes;

use App\Enums\AuditAction;
use App\Enums\PremadeTextKey;
use App\Models\PremadeText;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Support\Audit\AuditLog;
use App\Support\Text\Placeholders;

/**
 * Writes submitted builder content onto a quote version. Shared by saving the
 * current version in place and by saving as a new version, so the two can never
 * disagree about what a version is made of.
 */
final class SaveQuoteVersion
{
    /**
     * @param  Quote  $quote  the quote this version belongs to, for the placeholders in its texts
     * @param  array<string, mixed>  $data
     */
    public function handle(Quote $quote, QuoteVersion $version, array $data): QuoteVersion
    {
        // Loaded rather than lazily reached for: saving a quote can have just
        // moved it to a different customer, and the snapshot below has to
        // greet the one it is going to now.
        $quote->load('customer');

        $isNew = ! $version->exists;
        $before = $isNew ? [] : $this->snapshot($version);

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
            //
            // Placeholders are filled here for the same reason, and not when
            // the page or the PDF is rendered: a snapshot that still had to be
            // resolved would be a hole straight through the guarantee above.
            'intro_text_snapshot' => $this->snapshotText(PremadeTextKey::Intro, $quote),
            'footer_text_snapshot' => $this->snapshotText(PremadeTextKey::Footer, $quote),
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

        $version->load('lineItems.taxClass');

        $this->record($version, $isNew, $before);

        return $version;
    }

    /**
     * One of the global texts, with its placeholders turned into this quote's
     * customer.
     */
    private function snapshotText(PremadeTextKey $key, Quote $quote): string
    {
        return Placeholders::fill(PremadeText::contentFor($key), $quote->customer);
    }

    /**
     * Logged here rather than from the version's own model events, because the
     * lines are most of what a version is and none of them are written yet when
     * those events fire. Editing only the lines leaves the version row
     * untouched, so an event-driven log would miss the edit entirely.
     *
     * @param  array<string, mixed>  $before
     */
    private function record(QuoteVersion $version, bool $isNew, array $before): void
    {
        $after = $this->snapshot($version);

        if ($isNew) {
            AuditLog::record($version, AuditAction::Created, [
                ...$version->auditContext(),
                'attributes' => $after,
            ]);

            return;
        }

        $changes = [];

        foreach ($after as $attribute => $value) {
            if ($value !== ($before[$attribute] ?? null)) {
                $changes[$attribute] = ['from' => $before[$attribute] ?? null, 'to' => $value];
            }
        }

        // Reopening a quote and saving it unchanged is not an event.
        if ($changes === []) {
            return;
        }

        AuditLog::record($version, AuditAction::Updated, [
            ...$version->auditContext(),
            'changes' => $changes,
        ]);
    }

    /**
     * A version as a person sees it: the discount, the override, and the lines.
     *
     * The text snapshots are left out on purpose. They are copies of the global
     * texts, whose own edits are already logged, and a whole intro paragraph in
     * every payload would bury the change someone is actually looking for.
     *
     * @return array<string, mixed>
     */
    private function snapshot(QuoteVersion $version): array
    {
        return [
            'discount_type' => $version->discount_type?->value,
            'discount_value' => $version->discount_value,
            'rounding_override' => $version->rounding_override,
            'line_items' => $version->lineItems()->orderBy('id')->get()->map(fn ($lineItem): array => [
                'name' => $lineItem->name,
                'quantity' => $lineItem->quantity,
                'unit_price_ex_vat' => $lineItem->unit_price_ex_vat,
                'tax_class_id' => $lineItem->tax_class_id,
                'discount_type' => $lineItem->discount_type?->value,
                'discount_value' => $lineItem->discount_value,
            ])->all(),
        ];
    }
}
