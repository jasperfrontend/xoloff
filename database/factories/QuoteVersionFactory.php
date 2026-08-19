<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Quote;
use App\Models\QuoteVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteVersion>
 */
class QuoteVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'version_number' => 1,
            'discount_type' => null,
            'discount_value' => null,
            'rounding_override' => null,
        ];
    }

    public function withPercentageDiscount(float $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::Percentage,
            'discount_value' => $percentage,
        ]);
    }

    public function withFixedDiscount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::Fixed,
            'discount_value' => $amount,
        ]);
    }

    public function withRoundingOverride(float $total): static
    {
        return $this->state(fn (array $attributes) => [
            'rounding_override' => $total,
        ]);
    }
}
