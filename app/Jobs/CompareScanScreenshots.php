<?php

namespace App\Jobs;

use App\Actions\Scans\ApplyScanComparison;
use App\Enums\ScanStatus;
use App\Models\Scan;
use App\Models\Screenshot;
use App\Services\ScreenshotAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CompareScanScreenshots implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * Create a new job instance.
     */
    public function __construct(public Scan $scan) {}

    /**
     * Execute the job.
     */
    public function handle(ScreenshotAnalyzer $analyzer, ApplyScanComparison $applyComparison): void
    {
        $disk = Storage::disk('local');

        $screenshots = $this->scan->screenshots()
            ->whereNotNull('md5')
            ->chronological()
            ->get(['id', 'path', 'md5', 'phash'])
            ->map(fn (Screenshot $screenshot) => [
                'id' => $screenshot->id,
                'path' => $disk->path($screenshot->path),
                'md5' => (string) $screenshot->md5,
                'phash' => (string) $screenshot->phash,
            ])
            ->all();

        $references = Screenshot::query()
            ->whereNotNull('md5')
            ->where('scan_id', '!=', $this->scan->id)
            ->whereIn('scan_id', Scan::select('id')->where('employee_id', $this->scan->employee_id))
            ->orderBy('id')
            ->get(['id', 'md5', 'phash'])
            ->map(fn (Screenshot $screenshot) => [
                'id' => $screenshot->id,
                'md5' => (string) $screenshot->md5,
                'phash' => (string) $screenshot->phash,
            ])
            ->all();

        $applyComparison->handle($this->scan, $analyzer->compare(array_values($screenshots), array_values($references)));
    }

    /**
     * Mark the scan as failed once all attempts are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $this->scan->update([
            'status' => ScanStatus::Failed,
            'error' => 'Analisis gagal: '.($exception?->getMessage() ?? 'kesalahan tidak diketahui'),
        ]);
    }
}
