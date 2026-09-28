<?php

namespace Database\Factories;

use App\Models\ScanCategory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanCategory>
 */
class ScanCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'team_id' => Team::factory(),
            'name' => ucfirst($name),
            'prompt' => "a screenshot of {$name}",
            'keywords' => [$name],
            'is_productive' => true,
            'is_enabled' => true,
        ];
    }

    /**
     * Indicate that the category is not work related.
     */
    public function nonProductive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_productive' => false,
        ]);
    }

    /**
     * Indicate that the category is switched off.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
