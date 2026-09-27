<?php

namespace App\Actions\Scans;

use App\Enums\FindingType;
use App\Enums\ScreenshotActivity;
use App\Models\Scan;
use App\Models\ScanFinding;
use App\Models\Screenshot;

/**
 * Collects everything the Excel and PDF reports show for one scan.
 */
class BuildScanReport
{
    /**
     * @return array{
     *     scan: Scan,
     *     summary: list<array{0: string, 1: string|int}>,
     *     findings: list<array{finding: ScanFinding, screenshot: Screenshot|null, row: list<string>}>,
     *     screenshots: list<list<string|int|float|null>>,
     * }
     */
    public function handle(Scan $scan): array
    {
        $scan->loadMissing('employee:id,name');

        $screenshots = $scan->screenshots()->chronological()->get();
        $byId = $screenshots->keyBy('id');
        $findings = $scan->findings()
            ->with(['relatedScreenshot:id,scan_id,original_name,taken_at,thumbnail_path', 'relatedScreenshot.scan:id,work_date,created_at'])
            ->get()
            ->sortBy(fn (ScanFinding $finding) => $byId->get($finding->screenshot_id)?->taken_at->timestamp ?? PHP_INT_MAX)
            ->values();

        $activityCounts = collect(ScreenshotActivity::cases())
            ->mapWithKeys(fn (ScreenshotActivity $activity) => [
                $activity->value => $screenshots->filter(fn (Screenshot $screenshot) => ($screenshot->activity ?? ScreenshotActivity::Unknown) === $activity)->count(),
            ]);
        $measured = $screenshots->count() - $activityCounts[ScreenshotActivity::Unknown->value];
        $changed = $activityCounts[ScreenshotActivity::Active->value] + $activityCounts[ScreenshotActivity::Low->value];
        $timed = $screenshots->whereNotNull('taken_at');

        $summary = [
            ['Karyawan', $scan->employee->name],
            ['Tanggal kerja', $scan->work_date?->settings(['locale' => 'id'])->translatedFormat('l, j F Y') ?? '-'],
            ['Rentang waktu', $timed->isEmpty() ? '-' : $timed->first()->taken_at->format('d-m-Y H:i').' – '.$timed->last()->taken_at->format('d-m-Y H:i')],
            ['Total screenshot', $screenshots->count()],
            ['Layar berubah (aktif)', $measured > 0 ? number_format($changed / $measured * 100, 1, ',', '.').'%' : '-'],
            ['Total celah waktu (menit)', (int) $findings->where('type', FindingType::TimeGap)->sum(fn (ScanFinding $finding) => $finding->details['minutes'] ?? 0)],
        ];

        foreach (ScreenshotActivity::cases() as $activity) {
            $summary[] = ['Status: '.$activity->label(), $activityCounts[$activity->value]];
        }

        foreach (FindingType::cases() as $type) {
            $summary[] = ['Temuan: '.$type->label(), $findings->where('type', $type)->count()];
        }

        $summary[] = ['Dianalisis pada', $scan->analyzed_at?->format('d-m-Y H:i') ?? '-'];

        return [
            'scan' => $scan,
            'summary' => $summary,
            'findings' => array_values($findings->map(fn (ScanFinding $finding) => [
                'finding' => $finding,
                'screenshot' => $byId->get($finding->screenshot_id),
                'row' => [
                    $byId->get($finding->screenshot_id)?->taken_at?->format('H:i') ?? '--:--',
                    $finding->type->label(),
                    $finding->description(),
                    (string) $byId->get($finding->screenshot_id)?->original_name,
                    (string) $finding->relatedScreenshot?->original_name,
                ],
            ])->all()),
            'screenshots' => array_values($screenshots->map(fn (Screenshot $screenshot) => [
                $screenshot->taken_at?->format('d-m-Y H:i:s') ?? '-',
                $screenshot->taken_at_source->label(),
                $screenshot->original_name,
                $screenshot->screen_clock ?? '-',
                ($screenshot->activity ?? ScreenshotActivity::Unknown)->label(),
                $screenshot->change_ratio === null ? null : round((float) $screenshot->change_ratio * 100, 2),
                $screenshot->similarity_group,
                $screenshot->error,
            ])->all()),
        ];
    }
}
