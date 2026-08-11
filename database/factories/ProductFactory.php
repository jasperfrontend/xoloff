<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => rtrim(fake()->sentence(3), '.'),
            'price_ex_vat' => fake()->randomFloat(2, 10, 5000),
            'tax_class_id' => TaxClass::factory(),
            'category_id' => ProductCategory::factory(),
        ];
    }

    public function withoutCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => null,
        ]);
    }
}
