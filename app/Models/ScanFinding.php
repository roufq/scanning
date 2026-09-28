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
     * Describe the finding for people reviewing the scan.
     */
    public function description(): string
    {
        $details = $this->details ?? [];
        $related = $this->relatedScreenshot;
        $relatedTime = $related?->taken_at?->format('H:i') ?? '--:--';

        return match ($this->type) {
            FindingType::ExactDuplicate => "File identik dengan {$related?->original_name} ({$relatedTime}).",
            FindingType::Recycled => sprintf(
                '%s dengan screenshot dari scan %s (%s).',
                ($details['exact'] ?? false) ? 'File sama persis' : 'Gambar hampir identik',
                ($related?->scan->work_date ?? $related?->scan->created_at)?->settings(['locale' => 'id'])->translatedFormat('D, j M Y') ?? '-',
                $relatedTime,
            ),
            FindingType::Idle => sprintf('Layar hanya berubah %s dibanding screenshot %s.', $this->percent($details['change_ratio'] ?? 0), $relatedTime),
            FindingType::MouseJiggler => sprintf(
                'Hanya posisi kursor yang berubah (%s layar) dibanding screenshot %s, beruntun beberapa kali.',
                $this->percent($details['change_ratio'] ?? 0),
                $relatedTime,
            ),
            FindingType::TimeGap => sprintf(
                'Tidak ada screenshot selama %d menit (%s – %s).',
                $details['minutes'] ?? 0,
                substr((string) ($details['from'] ?? ''), 11, 5),
                substr((string) ($details['to'] ?? ''), 11, 5),
            ),
            FindingType::NonWork => ($details['source'] ?? null) === 'keyword'
                ? sprintf('Judul jendela/tab memuat "%s" → kategori %s.', $details['keyword'] ?? '', $details['category'] ?? '-')
                : sprintf('AI mengenali layar sebagai %s (keyakinan %s).', $details['category'] ?? '-', $this->percent((float) ($details['confidence'] ?? 0))),
            FindingType::ClockMismatch => sprintf(
                'Jam di layar %s, tetapi waktu %s %s (selisih %d menit).',
                $details['screen_clock'] ?? '-',
                ($details['source'] ?? '') === 'filename' ? 'di nama file' : 'file',
                $details['file_time'] ?? '-',
                abs((int) ($details['minutes'] ?? 0)),
            ),
        };
    }

    /**
     * Format a 0-1 ratio as a percentage.
     */
    protected function percent(float|int $ratio): string
    {
        return number_format($ratio * 100, $ratio < 0.01 ? 2 : 1, ',', '.').'%';
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
