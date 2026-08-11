<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSpec;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSpec>
 */
class ProductSpecFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'key' => fake()->randomElement(['Billing period', 'Startup cost', 'Contract duration', 'Storage', 'Bandwidth']),
            'value' => fake()->randomElement(['Monthly', 'Yearly', '€150', '12 months', '50 GB', 'Unmetered']),
        ];
    }
}
