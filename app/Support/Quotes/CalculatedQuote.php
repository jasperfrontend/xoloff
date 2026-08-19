<?php

namespace App\Support\Quotes;

/**
 * The full result of pricing one quote version. Every money value is a
 * fixed-precision string with two decimals, never a float.
 */
final readonly class CalculatedQuote
{
    /**
     * @param  array<int, CalculatedLine>  $lines
     * @param  array<int, TaxClassTotal>  $taxClassTotals
     */
    public function __construct(
        public array $lines,
        public array $taxClassTotals,
        public string $subtotalBeforeQuoteDiscount,
        public string $quoteDiscount,
        public string $subtotal,
        public string $vatTotal,
        public string $calculatedTotal,
        public ?string $roundingOverride,
        public string $total,
    ) {}

    /**
     * When true, `total` is the override and `calculatedTotal` is what the sum
     * would otherwise have been. The override replaces it outright for display
     * and PDF, with no reconciliation line (SPEC §5).
     */
    public function isOverridden(): bool
    {
        return $this->roundingOverride !== null;
    }
}
