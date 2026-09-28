<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-team overrides of the analysis thresholds in config/scanning.php.
 *
 * @property int $id
 * @property int $team_id
 * @property array<string, int|float|bool> $values
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'values'])]
class ScanSetting extends Model
{
    /**
     * The settings a team may change, with their validation rules.
     *
     * @var array<string, list<string>>
     */
    public const EDITABLE = [
        'idle_change_ratio' => ['required', 'numeric', 'min:0', 'max:0.2'],
        'low_change_ratio' => ['required', 'numeric', 'min:0', 'max:0.5', 'gte:idle_change_ratio'],
        'time_gap_minutes' => ['required', 'integer', 'min:1', 'max:600'],
        'clock_mismatch_minutes' => ['required', 'integer', 'min:1', 'max:240'],
        'jiggler_min_streak' => ['required', 'integer', 'min:1', 'max:50'],
        'similar_distance' => ['required', 'integer', 'min:0', 'max:32'],
        'read_screen_clock' => ['required', 'boolean'],
        'classify_screenshots' => ['required', 'boolean'],
        'ai_min_confidence' => ['required', 'numeric', 'min:0', 'max:1'],
    ];

    /**
     * The effective analysis settings for a team: config defaults merged with the team's overrides.
     *
     * @return array<string, mixed>
     */
    public static function valuesFor(int $teamId): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('scanning');
        $overrides = static::firstWhere('team_id', $teamId)->values ?? [];

        return array_replace($defaults, array_intersect_key($overrides, self::EDITABLE));
    }

    /**
     * Get the team the settings belong to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'values' => 'array',
        ];
    }
}
