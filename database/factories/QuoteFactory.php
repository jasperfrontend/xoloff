<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
        ];
    }

    /**
     * A quote that has been given to the customer. The columns that record
     * how and until when arrive with the rest of M4.
     */
    public function sent(): static
    {
        return $this->state(['status' => QuoteStatus::Sent]);
    }
}
