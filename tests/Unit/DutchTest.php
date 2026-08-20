<?php

namespace Tests\Unit;

use App\Support\Formatting\Dutch;
use PHPUnit\Framework\TestCase;

/**
 * The PDF is the one place the browser cannot do the formatting, so this has to
 * agree with resources/js/lib/money.ts. A quote that reads differently on
 * screen and on paper is a quote nobody trusts.
 */
class DutchTest extends TestCase
{
    public function test_it_separates_thousands_with_a_stop_and_decimals_with_a_comma()
    {
        $this->assertSame('4.682,70', Dutch::amount('4682.70'));
        $this->assertSame('182,70', Dutch::amount('182.70'));
        $this->assertSame('0,00', Dutch::amount('0'));
    }

    public function test_it_always_shows_two_decimals()
    {
        $this->assertSame('90,00', Dutch::amount('90'));
        $this->assertSame('90,70', Dutch::amount('90.7'));
    }

    /**
     * A non-breaking space, so the symbol is never left alone at the end of a
     * line in a document nobody can reflow afterwards.
     */
    public function test_money_keeps_the_symbol_with_its_amount()
    {
        $this->assertSame("€\u{00A0}4.682,70", Dutch::money('4682.70'));
        $this->assertStringNotContainsString('€ ', Dutch::money('90'));
    }

    public function test_percentages_read_the_same_way()
    {
        $this->assertSame('21,00%', Dutch::percentage('21.00'));
        $this->assertSame('0,00%', Dutch::percentage('0.00'));
    }

    /**
     * A discount that was never set arrives as null, and printing "€ 0,00"
     * would be a claim rather than a blank.
     */
    public function test_nothing_reads_as_zero_rather_than_as_an_error()
    {
        $this->assertSame('0,00', Dutch::amount(null));
        $this->assertSame("€\u{00A0}0,00", Dutch::money(null));
    }
}
