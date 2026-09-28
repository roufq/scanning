<?php

namespace App\Models;

use Database\Factories\ScanCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An application category the AI and keyword matcher sort screenshots into.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $prompt
 * @property list<string>|null $keywords
 * @property bool $is_productive
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'prompt', 'keywords', 'is_productive', 'is_enabled'])]
class ScanCategory extends Model
{
    /** @use HasFactory<ScanCategoryFactory> */
    use HasFactory;

    /**
     * Create the default categories for a team that has none yet.
     */
    public static function ensureDefaultsFor(Team $team): void
    {
        if ($team->scanCategories()->exists()) {
            return;
        }

        /** @var list<array{name: string, prompt: string, keywords: list<string>, is_productive: bool}> $defaults */
        $defaults = config('scanning.default_categories');

        $team->scanCategories()->createMany($defaults);
    }

    /**
     * Find the first keyword that appears as a whole word in the given text.
     */
    public function matchingKeyword(string $text): ?string
    {
        foreach ($this->keywords ?? [] as $keyword) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($keyword), '/').'(?![\p{L}\p{N}])/u';

            if ($keyword !== '' && preg_match($pattern, mb_strtolower($text))) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Get the team the category belongs to.
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
            'keywords' => 'array',
            'is_productive' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }
}
