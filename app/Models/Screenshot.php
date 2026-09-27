<?php

namespace App\Models;

use App\Enums\ScreenshotActivity;
use App\Enums\TimestampSource;
use Database\Factories\ScreenshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scan_id
 * @property string $original_name
 * @property string $path
 * @property string|null $thumbnail_path
 * @property Carbon|null $taken_at
 * @property TimestampSource $taken_at_source
 * @property string|null $md5
 * @property string|null $phash
 * @property int|null $width
 * @property int|null $height
 * @property int $file_size
 * @property string|null $change_ratio
 * @property list<float>|null $change_bbox
 * @property int|null $similarity_group
 * @property ScreenshotActivity|null $activity
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Scan $scan
 */
#[Fillable([
    'scan_id', 'original_name', 'path', 'thumbnail_path', 'taken_at', 'taken_at_source', 'md5', 'phash',
    'width', 'height', 'file_size', 'change_ratio', 'change_bbox', 'similarity_group', 'activity', 'error',
])]
class Screenshot extends Model
{
    /** @use HasFactory<ScreenshotFactory> */
    use HasFactory;

    /**
     * Get the scan the screenshot belongs to.
     *
     * @return BelongsTo<Scan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * Order screenshots by capture time, falling back to the file name.
     *
     * @param  Builder<Screenshot>  $query
     */
    #[Scope]
    protected function chronological(Builder $query): void
    {
        $query->orderByRaw('taken_at is null')
            ->orderBy('taken_at')
            ->orderBy('original_name')
            ->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'taken_at_source' => TimestampSource::class,
            'change_ratio' => 'decimal:6',
            'change_bbox' => 'array',
            'activity' => ScreenshotActivity::class,
        ];
    }
}
