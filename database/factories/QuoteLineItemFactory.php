<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\QuoteLineItem;
use App\Models\QuoteVersion;
use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLineItem>
 */
class QuoteLineItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_version_id' => QuoteVersion::factory(),
            'product_id' => null,
            'name' => rtrim(fake()->sentence(3), '.'),
            'specs' => null,
            'quantity' => 1,
            'unit_price_ex_vat' => fake()->randomFloat(2, 10, 5000),
            'tax_class_id' => TaxClass::factory(),
            'discount_type' => null,
            'discount_value' => null,
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
}
