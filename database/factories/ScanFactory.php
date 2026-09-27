<?php

namespace Database\Factories;

use App\Enums\ScanStatus;
use App\Models\Employee;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scan>
 */
class ScanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'team_id' => fn (array $attributes) => Employee::whereKey($attributes['employee_id'])->value('team_id'),
            'status' => ScanStatus::Uploading,
            'work_date' => fake()->date(),
        ];
    }

    /**
     * Indicate that the scan has been analyzed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Completed,
            'analyzed_at' => now(),
        ]);
    }

    /**
     * Indicate that the scan is being analyzed.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScanStatus::Processing,
        ]);
    }
}
