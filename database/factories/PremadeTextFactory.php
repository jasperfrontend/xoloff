<?php

namespace Database\Factories;

use App\Enums\PremadeTextKey;
use App\Models\PremadeText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PremadeText>
 */
class PremadeTextFactory extends Factory
{
    protected $model = PremadeText::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => PremadeTextKey::Intro,
            'content' => '<p>'.fake()->sentence().'</p>',
        ];
    }

    public function intro(): static
    {
        return $this->state(['key' => PremadeTextKey::Intro]);
    }

    public function footer(): static
    {
        return $this->state(['key' => PremadeTextKey::Footer]);
    }
}
