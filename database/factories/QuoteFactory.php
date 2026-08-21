<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
     * A quote that has been given to the customer: a link, a moment it went
     * out, and a window it stands until.
     *
     * @param  int  $validForDays  negative to produce a link whose window has already closed
     */
    public function sent(int $validForDays = 30): static
    {
        return $this->state([
            'status' => QuoteStatus::Sent,
            'magic_link_token' => Str::random(64),
            'sent_at' => CarbonImmutable::now(),
            'valid_until' => CarbonImmutable::today()->addDays($validForDays),
        ]);
    }
}
