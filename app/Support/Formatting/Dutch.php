<?php

namespace App\Support\Formatting;

/**
 * Numbers as they are read in the Netherlands: a comma for the decimal
 * separator, a full stop for thousands, always two decimals.
 *
 * The counterpart of resources/js/lib/money.ts, for the one place the browser
 * cannot do the formatting - the quote PDF, which is rendered from a Blade
 * template. Both sides read the same canonical decimal strings the calculation
 * engine produces, and neither ever sends a formatted value back.
 */
final class Dutch
{
    /**
     * "4682.70" becomes "4.682,70".
     */
    public static function amount(string|float|int|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, ',', '.');
    }

    /**
     * "4682.70" becomes "€ 4.682,70", joined by a non-breaking space so the
     * symbol is never orphaned at the end of a line.
     */
    public static function money(string|float|int|null $value): string
    {
        return "€\u{00A0}".self::amount($value);
    }

    /**
     * "21.00" becomes "21,00%".
     */
    public static function percentage(string|float|int|null $value): string
    {
        return self::amount($value).'%';
    }
}
