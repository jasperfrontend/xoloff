<?php

namespace App\Enums;

/**
 * How a discount value is interpreted, on both quote lines and whole quotes
 * (SPEC §3). Stored as a string so the database stays readable.
 */
enum DiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
