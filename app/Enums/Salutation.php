<?php

namespace App\Enums;

/**
 * How a contact is addressed, when they are addressed formally at all.
 *
 * The bare word rather than "de heer" or "dhr.", so the copy around it stays
 * whoever wrote the quote text's business: "Geachte heer Jansen" and "Aan de
 * heer Jansen" want different things in front of the same name, and only one
 * of those can be baked in here.
 *
 * Leaving it off is a first-class choice, not a missing value - "Beste Daan"
 * wants no salutation at all - which is why the column is nullable rather than
 * carrying a third case for it.
 */
enum Salutation: string
{
    case Heer = 'heer';
    case Mevrouw = 'mevrouw';

    /**
     * How the option reads on the customer form. English, like the rest of the
     * screens the two of them use, even though the value itself is Dutch and
     * ends up in a Dutch sentence.
     */
    public function label(): string
    {
        return match ($this) {
            self::Heer => __('Mr (heer)'),
            self::Mevrouw => __('Ms (mevrouw)'),
        };
    }
}
