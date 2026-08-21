<?php

namespace Database\Factories;

use App\Enums\Salutation;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'salutation' => fake()->randomElement(Salutation::cases()),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'billing_address' => fake()->streetAddress()."\n".fake()->postcode().' '.fake()->city(),
            'country' => 'NL',
        ];
    }
}
