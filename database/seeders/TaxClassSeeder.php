<?php

namespace Database\Seeders;

use App\Models\TaxClass;
use Illuminate\Database\Seeder;

/**
 * The three Dutch tax treatments named in SPEC §3, as a starting point.
 *
 * These are ordinary editable rows, not a fixed enum - tax classes stay freely
 * extensible so Xolution can sell other product types later. Idempotent.
 */
class TaxClassSeeder extends Seeder
{
    public function run(): void
    {
        $taxClasses = [
            ['name' => 'Standard 21%', 'percentage' => 21.00],
            ['name' => 'Reduced 9%', 'percentage' => 9.00],
            ['name' => 'Zero-rated / reverse charge', 'percentage' => 0.00],
        ];

        foreach ($taxClasses as $taxClass) {
            TaxClass::updateOrCreate(
                ['name' => $taxClass['name']],
                ['percentage' => $taxClass['percentage']],
            );
        }
    }
}
