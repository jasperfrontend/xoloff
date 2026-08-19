<?php

namespace App\Support\Quotes;

/**
 * The net and VAT owed for one tax class on a quote. A single quote can carry
 * several of these, because lines each pick their own tax class (SPEC §5).
 */
final readonly class TaxClassTotal
{
    public function __construct(
        public int $taxClassId,
        public string $name,
        public string $percentage,
        public string $net,
        public string $vat,
    ) {}
}
