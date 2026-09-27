<?php

namespace App\Models;

use App\Enums\FindingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $scan_id
 * @property int $screenshot_id
 * @property int|null $related_screenshot_id
 * @property FindingType $type
 * @property string|null $score
 * @property array<string, mixed>|null $details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Scan $scan
 * @property-read Screenshot $screenshot
 * @property-read Screenshot|null $relatedScreenshot
 */
#[Fillable(['scan_id', 'screenshot_id', 'related_screenshot_id', 'type', 'score', 'details'])]
class ScanFinding extends Model
{
    /**
     * Get the scan the finding belongs to.
     *
     * @return BelongsTo<Scan, $this>
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    /**
     * Get the screenshot the finding is about.
     *
     * @return BelongsTo<Screenshot, $this>
     */
    public function screenshot(): BelongsTo
    {
        return $this->belongsTo(Screenshot::class);
    }

    /**
     * Get the screenshot the finding was matched against.
     *
     * @return BelongsTo<Screenshot, $this>
     */
    public function relatedScreenshot(): BelongsTo
    {
        return $this->belongsTo(Screenshot::class, 'related_screenshot_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FindingType::class,
            'score' => 'decimal:4',
            'details' => 'array',
        ];
    }
}
