<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Models\Generation;
use App\Models\User;
use App\Pricing\Nanodollars;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => GenerationStatus::Pending,
            'prompt' => fake()->sentence(),
            'size' => Size::Square,
            'quality' => Quality::Medium,
            'reference_images' => [],
            'estimated_price' => Nanodollars::fromDecimal('0.068000000'),
            'estimated_price_max' => Nanodollars::fromDecimal('0.090000000'),
        ];
    }

    public function status(GenerationStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'provider_id' => $status === GenerationStatus::Pending
                ? null
                : 'gen_'.fake()->unique()->regexify('[0-9A-Z]{26}'),
        ]);
    }
}
