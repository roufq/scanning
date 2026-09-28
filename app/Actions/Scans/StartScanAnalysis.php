<?php

namespace App\Actions\Scans;

use App\Enums\ScanStatus;
use App\Jobs\CompareScanScreenshots;
use App\Jobs\ExtractScreenshotFeatures;
use App\Models\Scan;
use App\Models\ScanCategory;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

class StartScanAnalysis
{
    /**
     * Queue feature extraction for every screenshot, followed by the cross-screenshot comparison.
     */
    public function handle(Scan $scan): void
    {
        ScanCategory::ensureDefaultsFor($scan->team);

        $scan->update([
            'status' => ScanStatus::Queued,
            'processed_count' => 0,
            'error' => null,
        ]);

        $jobs = $scan->screenshots()
            ->orderBy('id')
            ->pluck('id')
            ->chunk(config('scanning.extract_chunk_size'))
            ->map(fn ($ids) => new ExtractScreenshotFeatures($scan->id, array_values($ids->map(fn (mixed $id): int => (int) $id)->all())))
            ->all();

        $scanId = $scan->id;

        $batch = Bus::batch($jobs)
            ->name("scan-{$scanId}")
            ->then(function () use ($scanId) {
                CompareScanScreenshots::dispatch(Scan::findOrFail($scanId));
            })
            ->catch(function (Batch $batch, ?Throwable $exception) use ($scanId) {
                Scan::whereKey($scanId)->update([
                    'status' => ScanStatus::Failed,
                    'error' => 'Ekstraksi gambar gagal: '.($exception?->getMessage() ?? 'kesalahan tidak diketahui'),
                ]);
            })
            ->dispatch();

        $scan->update(['batch_id' => $batch->id]);
    }
}
