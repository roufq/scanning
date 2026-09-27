<?php

namespace App\Jobs;

use App\Enums\ScanStatus;
use App\Models\Scan;
use App\Models\Screenshot;
use App\Services\ScreenshotAnalyzer;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ExtractScreenshotFeatures implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  list<int>  $screenshotIds
     */
    public function __construct(public int $scanId, public array $screenshotIds) {}

    /**
     * Execute the job.
     */
    public function handle(ScreenshotAnalyzer $analyzer): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        Scan::whereKey($this->scanId)
            ->where('status', ScanStatus::Queued)
            ->update(['status' => ScanStatus::Processing]);

        $disk = Storage::disk('local');
        $screenshots = Screenshot::whereKey($this->screenshotIds)->get()->keyBy('id');

        $results = $analyzer->extract(array_values($screenshots->map(fn (Screenshot $screenshot) => [
            'id' => $screenshot->id,
            'path' => $disk->path($screenshot->path),
            'thumbnail_path' => $disk->path($this->thumbnailPath($screenshot)),
        ])->all()));

        foreach ($results as $result) {
            $screenshot = $screenshots->get($result['id']);

            if ($screenshot === null) {
                continue;
            }

            $screenshot->update($result['error'] === null ? [
                'md5' => $result['md5'] ?? null,
                'phash' => $result['phash'] ?? null,
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
                'screen_clock' => $result['clock']['time'] ?? null,
                'screen_clock_ambiguous' => $result['clock']['ambiguous'] ?? false,
                'thumbnail_path' => $this->thumbnailPath($screenshot),
                'error' => null,
            ] : ['error' => mb_substr($result['error'], 0, 255)]);
        }

        Scan::whereKey($this->scanId)->increment('processed_count', count($results));
    }

    /**
     * The storage path of the screenshot's thumbnail.
     */
    protected function thumbnailPath(Screenshot $screenshot): string
    {
        return "screenshots/{$screenshot->scan_id}/thumbs/{$screenshot->id}.jpg";
    }
}
