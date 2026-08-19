<?php

namespace App\Support\Quotes;

use App\Enums\DiscountType;
use App\Models\QuoteLineItem;
use App\Models\QuoteVersion;

/**
 * The money math (SPEC §5). This is the highest-risk code in the project, so it
 * is deliberately blunt: everything is done in integer cents, and the only
 * rounding is a single half-up helper applied at named points.
 *
 * Two rules decide the answers the spec leaves open:
 *
 * 1. Each line is rounded to cents as it is priced, and the rounded lines are
 *    then summed. The lines printed on a PDF therefore always add up to the
 *    printed subtotal, which is what a customer checking by hand expects.
 * 2. The quote-level discount is pushed down onto the individual lines in
 *    proportion to their net, so every line carries a final amount and VAT can
 *    be grouped by tax class afterwards.
 */
final class QuoteCalculator
{
    /**
     * Percentages are held as hundredths of a percent, so a rate is applied by
     * multiplying and then dividing by this.
     */
    private const PERCENT_SCALE = 10000;

    public function calculate(QuoteVersion $version): CalculatedQuote
    {
        $version->loadMissing('lineItems.taxClass');

        /** @var array<int, array<string, mixed>> $lines */
        $lines = [];

        foreach ($version->lineItems->sortBy('id') as $lineItem) {
            $lines[] = $this->priceLine($lineItem);
        }

        $subtotalBeforeQuoteDiscount = (int) array_sum(array_column($lines, 'net'));

        $quoteDiscount = $this->quoteDiscount($version, $subtotalBeforeQuoteDiscount);
        $shares = $this->allocate($quoteDiscount, array_column($lines, 'net'));

        foreach ($lines as $index => $line) {
            $lines[$index]['quote_discount_share'] = $shares[$index];
            $lines[$index]['final_net'] = $line['net'] - $shares[$index];
        }

        $taxClassTotals = $this->taxClassTotals($lines);

        $subtotal = $subtotalBeforeQuoteDiscount - $quoteDiscount;
        $vatTotal = (int) array_sum(array_column($taxClassTotals, 'vat'));
        $calculatedTotal = $subtotal + $vatTotal;

        $override = $version->rounding_override === null
            ? null
            : $this->toCents($version->rounding_override);

        return new CalculatedQuote(
            lines: array_map(fn (array $line): CalculatedLine => $this->toCalculatedLine($line), $lines),
            taxClassTotals: array_map(fn (array $total): TaxClassTotal => new TaxClassTotal(
                taxClassId: $total['tax_class_id'],
                name: $total['name'],
                percentage: $total['percentage'],
                net: $this->toMoney($total['net']),
                vat: $this->toMoney($total['vat']),
            ), $taxClassTotals),
            subtotalBeforeQuoteDiscount: $this->toMoney($subtotalBeforeQuoteDiscount),
            quoteDiscount: $this->toMoney($quoteDiscount),
            subtotal: $this->toMoney($subtotal),
            vatTotal: $this->toMoney($vatTotal),
            calculatedTotal: $this->toMoney($calculatedTotal),
            roundingOverride: $override === null ? null : $this->toMoney($override),
            total: $this->toMoney($override ?? $calculatedTotal),
        );
    }

    /**
     * SPEC §5 steps 1 and 2: line subtotal, then the line-level discount
     * against it, both pre-VAT.
     *
     * @return array<string, mixed>
     */
    private function priceLine(QuoteLineItem $lineItem): array
    {
        $quantity = $this->toCents($lineItem->quantity);
        $unitPrice = $this->toCents($lineItem->unit_price_ex_vat);

        // Quantity is held in hundredths too, so the product is scaled 100
        // times further than cents and has to come back down.
        $subtotal = $this->divideRounded($quantity * $unitPrice, 100);

        $discount = $this->discountAgainst(
            $subtotal,
            $lineItem->discount_type,
            $lineItem->discount_value,
        );

        $taxClass = $lineItem->taxClass;

        return [
            'line_item_id' => $lineItem->id,
            'name' => $lineItem->name,
            'quantity' => $lineItem->quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'line_discount' => $discount,
            'net' => $subtotal - $discount,
            'quote_discount_share' => 0,
            'final_net' => $subtotal - $discount,
            'tax_class_id' => $taxClass->id,
            'tax_class_name' => $taxClass->name,
            'tax_class_percentage' => (string) $taxClass->percentage,
        ];
    }

    /**
     * SPEC §5 step 4: the quote-level discount, against the summed line nets.
     */
    private function quoteDiscount(QuoteVersion $version, int $subtotal): int
    {
        return $this->discountAgainst(
            $subtotal,
            $version->discount_type,
            $version->discount_value,
        );
    }

    /**
     * A discount can never exceed what it is applied to, and can never be
     * negative. Validation should catch both, but the engine must not be the
     * thing that turns a typo into a negative total.
     */
    private function discountAgainst(int $amount, ?DiscountType $type, ?string $value): int
    {
        if ($type === null || $value === null) {
            return 0;
        }

        $discount = match ($type) {
            DiscountType::Percentage => $this->divideRounded(
                $amount * $this->toCents($value),
                self::PERCENT_SCALE,
            ),
            DiscountType::Fixed => $this->toCents($value),
        };

        return max(0, min($discount, $amount));
    }

    /**
     * Splits the quote-level discount across lines in proportion to their net.
     *
     * Rounding each share independently leaves a remainder of a few cents, so
     * it is handed out a cent at a time to the largest lines. Without this the
     * shares would not sum to the discount, and the printed figures would not
     * reconcile.
     *
     * @param  array<int, int>  $nets
     * @return array<int, int>
     */
    private function allocate(int $discount, array $nets): array
    {
        $subtotal = (int) array_sum($nets);

        if ($discount === 0 || $subtotal <= 0) {
            return array_fill(0, count($nets), 0);
        }

        $shares = array_map(
            fn (int $net): int => $this->divideRounded($discount * $net, $subtotal),
            $nets,
        );

        $remainder = $discount - (int) array_sum($shares);
        $step = $remainder < 0 ? -1 : 1;

        // Largest net first, so the stray cents land where they are least
        // visible.
        $order = array_keys($nets);
        usort($order, fn (int $a, int $b): int => $nets[$b] <=> $nets[$a]);

        while ($remainder !== 0) {
            $moved = false;

            foreach ($order as $index) {
                if ($remainder === 0) {
                    break;
                }

                $adjusted = $shares[$index] + $step;

                // A share cannot exceed its own line or go negative, otherwise
                // the line would end up with a negative net.
                if ($adjusted < 0 || $adjusted > $nets[$index]) {
                    continue;
                }

                $shares[$index] = $adjusted;
                $remainder -= $step;
                $moved = true;
            }

            // Nothing could absorb the rest, so stop rather than spin.
            if (! $moved) {
                break;
            }
        }

        return $shares;
    }

    /**
     * SPEC §5 step 5: VAT per tax class present on the quote, calculated on the
     * discounted amount. Ordered by rate descending, matching how tax classes
     * are listed elsewhere in the app.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function taxClassTotals(array $lines): array
    {
        /** @var array<int, array<string, mixed>> $totals */
        $totals = [];

        foreach ($lines as $line) {
            $id = $line['tax_class_id'];

            $totals[$id] ??= [
                'tax_class_id' => $id,
                'name' => $line['tax_class_name'],
                'percentage' => $line['tax_class_percentage'],
                'net' => 0,
                'vat' => 0,
            ];

            $totals[$id]['net'] += $line['final_net'];
        }

        foreach ($totals as $id => $total) {
            $totals[$id]['vat'] = $this->divideRounded(
                $total['net'] * $this->toCents($total['percentage']),
                self::PERCENT_SCALE,
            );
        }

        $ordered = array_values($totals);

        usort($ordered, fn (array $a, array $b): int => $this->toCents($b['percentage']) <=> $this->toCents($a['percentage']));

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function toCalculatedLine(array $line): CalculatedLine
    {
        return new CalculatedLine(
            lineItemId: $line['line_item_id'],
            name: $line['name'],
            quantity: $line['quantity'],
            unitPriceExVat: $this->toMoney($line['unit_price']),
            subtotal: $this->toMoney($line['subtotal']),
            lineDiscount: $this->toMoney($line['line_discount']),
            quoteDiscountShare: $this->toMoney($line['quote_discount_share']),
            net: $this->toMoney($line['final_net']),
            taxClassId: $line['tax_class_id'],
            taxClassName: $line['tax_class_name'],
            taxClassPercentage: $line['tax_class_percentage'],
        );
    }

    /**
     * Parses a fixed-precision decimal string into hundredths without ever
     * touching a float.
     */
    private function toCents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '0');

        $negative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '+-');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        $cents = (int) $whole * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function toMoney(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Integer division rounding half away from zero. This is the only place
     * rounding happens.
     */
    private function divideRounded(int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            return 0;
        }

        $negative = ($numerator < 0) !== ($denominator < 0);
        $numerator = abs($numerator);
        $denominator = abs($denominator);

        $result = intdiv($numerator * 2 + $denominator, $denominator * 2);

        return $negative ? -$result : $result;
    }
}
