<?php

namespace Database\Factories;

use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxClass>
 */
class TaxClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Tax '.fake()->unique()->numberBetween(1, 100000),
            'percentage' => 21.00,
        ];
    }

    /**
     * Zero-rated / reverse charge - the case a non-EU customer gets.
     */
    public function zeroRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'percentage' => 0.00,
        ]);
    }
}
