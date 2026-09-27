<?php

namespace App\Actions\Scans;

use App\Enums\FindingType;
use App\Enums\ScanStatus;
use App\Enums\ScreenshotActivity;
use App\Models\Scan;
use App\Models\Screenshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplyScanComparison
{
    /**
     * Store the analyzer's comparison result as screenshot activity levels and findings.
     *
     * @param  array{
     *     changes: list<array{id: int, previous_id: int, change_ratio: float, bbox: list<float>|null}>,
     *     duplicates: list<array{id: int, original_id: int}>,
     *     recycled: list<array{id: int, reference_id: int, distance: int, exact: bool}>,
     *     groups: list<list<int>>,
     * }  $result
     */
    public function handle(Scan $scan, array $result): void
    {
        /** @var Collection<int, Screenshot> $screenshots */
        $screenshots = Screenshot::query()
            ->where('scan_id', $scan->id)
            ->chronological()
            ->get()
            ->keyBy('id');

        $findings = [];
        $changes = collect($result['changes'])->keyBy('id');
        $duplicates = collect($result['duplicates'])->keyBy('id');
        $recycled = collect($result['recycled'])->keyBy('id');
        $groupByScreenshot = [];

        foreach ($result['groups'] as $index => $ids) {
            foreach ($ids as $id) {
                $groupByScreenshot[$id] = $index + 1;
            }
        }

        $groups = collect($groupByScreenshot);

        foreach ($screenshots as $screenshot) {
            $change = $changes->get($screenshot->id);

            $screenshot->change_ratio = $change ? (string) $change['change_ratio'] : null;
            $screenshot->change_bbox = $change['bbox'] ?? null;
            $screenshot->similarity_group = $groups->get($screenshot->id);
            $screenshot->activity = $this->activityFor($screenshot, $change, $duplicates->has($screenshot->id) || $recycled->has($screenshot->id));

            if ($duplicate = $duplicates->get($screenshot->id)) {
                $findings[] = $this->finding($scan, $screenshot->id, FindingType::ExactDuplicate, $duplicate['original_id'], 1.0);
            }

            if ($match = $recycled->get($screenshot->id)) {
                $findings[] = $this->finding($scan, $screenshot->id, FindingType::Recycled, $match['reference_id'], 1 - $match['distance'] / 64, [
                    'exact' => $match['exact'],
                    'distance' => $match['distance'],
                ]);
            }

            if ($change && $screenshot->activity === ScreenshotActivity::Idle) {
                $findings[] = $this->finding($scan, $screenshot->id, FindingType::Idle, $change['previous_id'], $change['change_ratio'], [
                    'change_ratio' => $change['change_ratio'],
                ]);
            }
        }

        array_push($findings, ...$this->timeGapFindings($scan, $screenshots));

        DB::transaction(function () use ($scan, $screenshots, $findings) {
            $scan->findings()->delete();

            $screenshots->each(fn (Screenshot $screenshot) => $screenshot->save());

            foreach (array_chunk($findings, 500) as $chunk) {
                $scan->findings()->insert($chunk);
            }

            $scan->update([
                'status' => ScanStatus::Completed,
                'error' => null,
                'analyzed_at' => now(),
            ]);
        });
    }

    /**
     * Classify how active the screen was compared with the previous screenshot.
     *
     * @param  array{id: int, previous_id: int, change_ratio: float, bbox: list<float>|null}|null  $change
     */
    protected function activityFor(Screenshot $screenshot, ?array $change, bool $isCopy): ScreenshotActivity
    {
        return match (true) {
            $screenshot->error !== null => ScreenshotActivity::Unknown,
            $isCopy => ScreenshotActivity::Duplicate,
            $change === null => ScreenshotActivity::Unknown,
            $change['change_ratio'] < config('scanning.idle_change_ratio') => ScreenshotActivity::Idle,
            $change['change_ratio'] < config('scanning.low_change_ratio') => ScreenshotActivity::Low,
            default => ScreenshotActivity::Active,
        };
    }

    /**
     * Find gaps between consecutive screenshots that are longer than the capture interval allows.
     *
     * @param  Collection<int, Screenshot>  $screenshots
     * @return list<array<string, mixed>>
     */
    protected function timeGapFindings(Scan $scan, Collection $screenshots): array
    {
        $findings = [];
        $previous = null;

        foreach ($screenshots->filter(fn (Screenshot $screenshot) => $screenshot->taken_at !== null) as $screenshot) {
            if ($previous?->taken_at !== null) {
                $minutes = (int) round($previous->taken_at->diffInMinutes($screenshot->taken_at));

                if ($minutes > config('scanning.time_gap_minutes')) {
                    $findings[] = $this->finding($scan, $screenshot->id, FindingType::TimeGap, $previous->id, $minutes, [
                        'minutes' => $minutes,
                        'from' => $previous->taken_at->toDateTimeString(),
                        'to' => $screenshot->taken_at->toDateTimeString(),
                    ]);
                }
            }

            $previous = $screenshot;
        }

        return $findings;
    }

    /**
     * Build a finding row for bulk insertion.
     *
     * @param  array<string, mixed>|null  $details
     * @return array<string, mixed>
     */
    protected function finding(Scan $scan, int $screenshotId, FindingType $type, ?int $relatedId, float|int $score, ?array $details = null): array
    {
        return [
            'scan_id' => $scan->id,
            'screenshot_id' => $screenshotId,
            'related_screenshot_id' => $relatedId,
            'type' => $type->value,
            'score' => round($score, 4),
            'details' => $details === null ? null : json_encode($details),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
