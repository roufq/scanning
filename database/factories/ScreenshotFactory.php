<?php

namespace Database\Factories;

use App\Enums\TimestampSource;
use App\Models\Scan;
use App\Models\Screenshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screenshot>
 */
class ScreenshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $takenAt = fake()->dateTimeThisMonth();

        return [
            'scan_id' => Scan::factory(),
            'original_name' => 'SS_'.$takenAt->format('Ymd_His').'.png',
            'path' => fn (array $attributes) => "screenshots/{$attributes['scan_id']}/originals/".fake()->uuid().'.png',
            'taken_at' => $takenAt,
            'taken_at_source' => TimestampSource::Filename,
            'file_size' => fake()->numberBetween(50_000, 900_000),
        ];
    }

    /**
     * Indicate that the analyzer already extracted the screenshot's features.
     */
    public function extracted(): static
    {
        return $this->state(fn (array $attributes) => [
            'md5' => fake()->md5(),
            'phash' => bin2hex(random_bytes(8)),
            'width' => 1920,
            'height' => 1080,
        ]);
    }
}
