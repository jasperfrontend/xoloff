<?php

namespace App\Support\Quotes;

/**
 * One quote line, fully priced. Every money value is a fixed-precision string
 * with two decimals, never a float.
 */
final readonly class CalculatedLine
{
    public function __construct(
        public ?int $lineItemId,
        public string $name,
        public string $quantity,
        public string $unitPriceExVat,
        public string $subtotal,
        public string $lineDiscount,
        public string $quoteDiscountShare,
        public string $net,
        public int $taxClassId,
        public string $taxClassName,
        public string $taxClassPercentage,
    ) {}
}
