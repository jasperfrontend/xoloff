<?php

namespace Tests\Feature;

use App\Models\QuoteLineItem;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use App\Support\Quotes\CalculatedQuote;
use App\Support\Quotes\QuoteCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_line_is_quantity_times_price_plus_vat()
    {
        $version = QuoteVersion::factory()->create();

        $this->line($version, quantity: 2, unitPrice: 100.00, percentage: 21.00);

        $result = $this->calculate($version);

        $this->assertSame('200.00', $result->subtotal);
        $this->assertSame('42.00', $result->vatTotal);
        $this->assertSame('242.00', $result->calculatedTotal);
        $this->assertSame('242.00', $result->total);
    }

    public function test_a_fractional_quantity_rounds_half_up()
    {
        $version = QuoteVersion::factory()->create();

        // 1.5 x 33.33 = 49.995, which must land on 50.00 rather than 49.99.
        $this->line($version, quantity: 1.5, unitPrice: 33.33, percentage: 21.00);

        $result = $this->calculate($version);

        $this->assertSame('50.00', $result->subtotal);
    }

    public function test_a_line_percentage_discount_is_applied_pre_vat()
    {
        $version = QuoteVersion::factory()->create();

        // Each line is 10.00 less 33.33%, which is 6.667 and rounds to 6.67.
        // Rounding per line is what makes the printed lines sum to the printed
        // subtotal: 6.67 x 3 = 20.01, not 20.00.
        for ($i = 0; $i < 3; $i++) {
            $this->line($version, quantity: 1, unitPrice: 10.00, percentage: 21.00, discountPercentage: 33.33);
        }

        $result = $this->calculate($version);

        $this->assertSame('6.67', $result->lines[0]->net);
        $this->assertSame('20.01', $result->subtotal);
        $this->assertSame('4.20', $result->vatTotal);
        $this->assertSame('24.21', $result->calculatedTotal);
    }

    public function test_a_line_fixed_discount_is_applied_pre_vat()
    {
        $version = QuoteVersion::factory()->create();

        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00, discountFixed: 25.00);

        $result = $this->calculate($version);

        $this->assertSame('25.00', $result->lines[0]->lineDiscount);
        $this->assertSame('75.00', $result->subtotal);
        $this->assertSame('15.75', $result->vatTotal);
        $this->assertSame('90.75', $result->calculatedTotal);
    }

    public function test_vat_is_calculated_per_tax_class_on_a_mixed_quote()
    {
        $version = QuoteVersion::factory()->create();

        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00);
        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 9.00);

        $result = $this->calculate($version);

        $this->assertCount(2, $result->taxClassTotals);

        // Ordered by rate descending.
        $this->assertSame('21.00', $result->taxClassTotals[0]->percentage);
        $this->assertSame('21.00', $result->taxClassTotals[0]->vat);
        $this->assertSame('9.00', $result->taxClassTotals[1]->percentage);
        $this->assertSame('9.00', $result->taxClassTotals[1]->vat);

        $this->assertSame('30.00', $result->vatTotal);
        $this->assertSame('230.00', $result->calculatedTotal);
    }

    public function test_a_zero_rated_line_adds_no_vat()
    {
        $version = QuoteVersion::factory()->create();

        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00);
        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 0.00);

        $result = $this->calculate($version);

        $this->assertSame('0.00', $result->taxClassTotals[1]->vat);
        $this->assertSame('100.00', $result->taxClassTotals[1]->net);
        $this->assertSame('21.00', $result->vatTotal);
        $this->assertSame('221.00', $result->calculatedTotal);
    }

    public function test_a_wholly_zero_rated_quote_has_no_vat_at_all()
    {
        $version = QuoteVersion::factory()->create();

        $this->line($version, quantity: 3, unitPrice: 250.00, percentage: 0.00);

        $result = $this->calculate($version);

        $this->assertSame('750.00', $result->subtotal);
        $this->assertSame('0.00', $result->vatTotal);
        $this->assertSame('750.00', $result->calculatedTotal);
    }

    public function test_a_quote_discount_is_split_across_tax_classes_before_vat()
    {
        $version = QuoteVersion::factory()->withPercentageDiscount(10.00)->create();

        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00);
        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 9.00);

        $result = $this->calculate($version);

        // Each line absorbs its proportional share, so each carries a
        // printable final amount.
        $this->assertSame('10.00', $result->lines[0]->quoteDiscountShare);
        $this->assertSame('90.00', $result->lines[0]->net);
        $this->assertSame('10.00', $result->lines[1]->quoteDiscountShare);
        $this->assertSame('90.00', $result->lines[1]->net);

        $this->assertSame('200.00', $result->subtotalBeforeQuoteDiscount);
        $this->assertSame('20.00', $result->quoteDiscount);
        $this->assertSame('180.00', $result->subtotal);

        $this->assertSame('18.90', $result->taxClassTotals[0]->vat);
        $this->assertSame('8.10', $result->taxClassTotals[1]->vat);
        $this->assertSame('27.00', $result->vatTotal);
        $this->assertSame('207.00', $result->calculatedTotal);
    }

    public function test_line_and_quote_discounts_stack()
    {
        $version = QuoteVersion::factory()->withPercentageDiscount(10.00)->create();

        // 100.00 less 20% is 80.00, less a further 10% at quote level is 72.00.
        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00, discountPercentage: 20.00);

        $result = $this->calculate($version);

        $this->assertSame('20.00', $result->lines[0]->lineDiscount);
        $this->assertSame('80.00', $result->subtotalBeforeQuoteDiscount);
        $this->assertSame('8.00', $result->quoteDiscount);
        $this->assertSame('72.00', $result->subtotal);
        $this->assertSame('15.12', $result->vatTotal);
        $this->assertSame('87.12', $result->calculatedTotal);
    }

    public function test_an_indivisible_quote_discount_still_reconciles_to_the_cent()
    {
        $version = QuoteVersion::factory()->withFixedDiscount(10.00)->create();

        // 10.00 split three ways is 3.333 each, so the shares round to 9.99 and
        // the stray cent has to be handed to a line.
        for ($i = 0; $i < 3; $i++) {
            $this->line($version, quantity: 1, unitPrice: 10.00, percentage: 21.00);
        }

        $result = $this->calculate($version);

        $shares = array_map(fn ($line): string => $line->quoteDiscountShare, $result->lines);

        $this->assertSame(['3.34', '3.33', '3.33'], $shares);
        $this->assertSame('10.00', $result->quoteDiscount);
        $this->assertSame('20.00', $result->subtotal);
        $this->assertLinesSumToSubtotal($result);
    }

    public function test_a_discount_can_never_exceed_what_it_applies_to()
    {
        $version = QuoteVersion::factory()->withFixedDiscount(9999.00)->create();

        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00, discountFixed: 500.00);

        $result = $this->calculate($version);

        $this->assertSame('100.00', $result->lines[0]->lineDiscount);
        $this->assertSame('0.00', $result->subtotal);
        $this->assertSame('0.00', $result->vatTotal);
        $this->assertSame('0.00', $result->calculatedTotal);
    }

    public function test_a_rounding_override_replaces_the_calculated_total()
    {
        $version = QuoteVersion::factory()->withRoundingOverride(240.00)->create();

        $this->line($version, quantity: 2, unitPrice: 100.00, percentage: 21.00);

        $result = $this->calculate($version);

        $this->assertTrue($result->isOverridden());
        $this->assertSame('240.00', $result->total);
        $this->assertSame('240.00', $result->roundingOverride);

        // The calculated figure is still reported, but it is not the total any
        // more. There is no reconciliation line and no adjustment entry.
        $this->assertSame('242.00', $result->calculatedTotal);
        $this->assertSame('200.00', $result->subtotal);
        $this->assertSame('42.00', $result->vatTotal);
    }

    public function test_a_quote_without_an_override_is_not_marked_overridden()
    {
        $version = QuoteVersion::factory()->create();

        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00);

        $result = $this->calculate($version);

        $this->assertFalse($result->isOverridden());
        $this->assertNull($result->roundingOverride);
        $this->assertSame($result->calculatedTotal, $result->total);
    }

    public function test_an_override_of_zero_is_honoured_rather_than_ignored()
    {
        $version = QuoteVersion::factory()->withRoundingOverride(0.00)->create();

        $this->line($version, quantity: 1, unitPrice: 100.00, percentage: 21.00);

        $result = $this->calculate($version);

        $this->assertTrue($result->isOverridden());
        $this->assertSame('0.00', $result->total);
        $this->assertSame('121.00', $result->calculatedTotal);
    }

    public function test_an_empty_quote_totals_zero()
    {
        $version = QuoteVersion::factory()->create();

        $result = $this->calculate($version);

        $this->assertSame([], $result->lines);
        $this->assertSame([], $result->taxClassTotals);
        $this->assertSame('0.00', $result->subtotal);
        $this->assertSame('0.00', $result->vatTotal);
        $this->assertSame('0.00', $result->calculatedTotal);
    }

    public function test_a_discount_on_an_empty_quote_does_not_divide_by_zero()
    {
        $version = QuoteVersion::factory()->withPercentageDiscount(10.00)->create();

        $result = $this->calculate($version);

        $this->assertSame('0.00', $result->quoteDiscount);
        $this->assertSame('0.00', $result->calculatedTotal);
    }

    public function test_lines_always_sum_to_the_subtotal_across_awkward_splits()
    {
        // Uneven line values and an indivisible percentage together are the
        // case most likely to leave a stray cent unaccounted for.
        $version = QuoteVersion::factory()->withPercentageDiscount(33.33)->create();

        foreach ([19.99, 5.01, 100.00, 0.07, 250.55] as $price) {
            $this->line($version, quantity: 3, unitPrice: $price, percentage: 21.00, discountPercentage: 7.77);
        }

        $result = $this->calculate($version);

        $this->assertLinesSumToSubtotal($result);
    }

    /**
     * The property the whole rounding decision exists to protect: whatever is
     * printed per line has to add up to the printed subtotal.
     */
    private function assertLinesSumToSubtotal(CalculatedQuote $result): void
    {
        $sum = 0;

        foreach ($result->lines as $line) {
            $sum += (int) round(((float) $line->net) * 100);
        }

        $this->assertSame(
            $result->subtotal,
            number_format($sum / 100, 2, '.', ''),
            'The line nets do not add up to the quote subtotal.',
        );
    }

    private function calculate(QuoteVersion $version): CalculatedQuote
    {
        return (new QuoteCalculator)->calculate($version->fresh());
    }

    private function line(
        QuoteVersion $version,
        float $quantity,
        float $unitPrice,
        float $percentage,
        ?float $discountPercentage = null,
        ?float $discountFixed = null,
    ): void {
        $factory = QuoteLineItem::factory();

        if ($discountPercentage !== null) {
            $factory = $factory->withPercentageDiscount($discountPercentage);
        }

        if ($discountFixed !== null) {
            $factory = $factory->withFixedDiscount($discountFixed);
        }

        $factory->create([
            'quote_version_id' => $version->id,
            'quantity' => $quantity,
            'unit_price_ex_vat' => $unitPrice,
            'tax_class_id' => TaxClass::factory()->create(['percentage' => $percentage]),
        ]);
    }
}
