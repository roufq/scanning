<?php

namespace App\Actions\Scans;

use App\Enums\FindingType;
use App\Enums\ScanStatus;
use App\Enums\ScreenshotActivity;
use App\Enums\TimestampSource;
use App\Models\Scan;
use App\Models\Screenshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type ChangeRegion array{bbox: list<float>, ratio: float}
 * @phpstan-type Change array{id: int, previous_id: int, change_ratio: float, bbox: list<float>|null, regions?: list<ChangeRegion>}
 */
class ApplyScanComparison
{
    /**
     * Store the analyzer's comparison result as screenshot activity levels and findings.
     *
     * @param  array{
     *     changes: list<Change>,
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
        /** @var Collection<int, Change> $changes */
        $changes = collect($result['changes'])->keyBy('id');
        $duplicates = collect($result['duplicates'])->keyBy('id');
        $recycled = collect($result['recycled'])->keyBy('id');
        $groupByScreenshot = [];

        foreach ($result['groups'] as $index => $ids) {
            foreach ($ids as $id) {
                $groupByScreenshot[$id] = $index + 1;
            }
        }

        foreach ($screenshots as $screenshot) {
            $change = $changes->get($screenshot->id);

            $screenshot->change_ratio = $change ? (string) $change['change_ratio'] : null;
            $screenshot->change_bbox = $change['bbox'] ?? null;
            $screenshot->change_regions = $change['regions'] ?? null;
            $screenshot->similarity_group = $groupByScreenshot[$screenshot->id] ?? null;
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

            if ($mismatch = $this->clockMismatch($screenshot)) {
                $findings[] = $this->finding($scan, $screenshot->id, FindingType::ClockMismatch, null, abs($mismatch['minutes']), $mismatch);
            }
        }

        $this->resolveCursorStreaks($screenshots);

        foreach ($screenshots as $screenshot) {
            $change = $changes->get($screenshot->id);
            $type = match ($screenshot->activity) {
                ScreenshotActivity::Idle => FindingType::Idle,
                ScreenshotActivity::CursorOnly => FindingType::MouseJiggler,
                default => null,
            };

            if ($change && $type) {
                $findings[] = $this->finding($scan, $screenshot->id, $type, $change['previous_id'], $change['change_ratio'], [
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
     * @param  Change|null  $change
     */
    protected function activityFor(Screenshot $screenshot, ?array $change, bool $isCopy): ScreenshotActivity
    {
        return match (true) {
            $screenshot->error !== null => ScreenshotActivity::Unknown,
            $isCopy => ScreenshotActivity::Duplicate,
            $change === null => ScreenshotActivity::Unknown,
            $this->isCursorOnly($change) => ScreenshotActivity::CursorOnly,
            $change['change_ratio'] < config('scanning.idle_change_ratio') => ScreenshotActivity::Idle,
            $change['change_ratio'] < config('scanning.low_change_ratio') => ScreenshotActivity::Low,
            default => ScreenshotActivity::Active,
        };
    }

    /**
     * Whether everything that changed fits in a few cursor-sized areas.
     *
     * @param  Change  $change
     */
    protected function isCursorOnly(array $change): bool
    {
        $regions = $change['regions'] ?? [];
        $maxSize = (float) config('scanning.cursor_max_region_size');

        return $regions !== []
            && count($regions) <= config('scanning.cursor_max_regions')
            && $change['change_ratio'] < config('scanning.low_change_ratio')
            && collect($regions)->every(fn (array $region) => $region['bbox'][2] - $region['bbox'][0] <= $maxSize
                && $region['bbox'][3] - $region['bbox'][1] <= $maxSize * 1.5);
    }

    /**
     * A single cursor-only screenshot is just an idle screen; only a streak points to a mouse jiggler.
     *
     * @param  Collection<int, Screenshot>  $screenshots
     */
    protected function resolveCursorStreaks(Collection $screenshots): void
    {
        $minimumStreak = (int) config('scanning.jiggler_min_streak');

        $screenshots->values()
            ->chunkWhile(fn (Screenshot $screenshot, int $index, Collection $chunk) => ($screenshot->activity === ScreenshotActivity::CursorOnly)
                === ($chunk->last()->activity === ScreenshotActivity::CursorOnly))
            ->filter(fn (Collection $run) => $run->first()->activity === ScreenshotActivity::CursorOnly && $run->count() < $minimumStreak)
            ->each(function (Collection $run) {
                foreach ($run as $screenshot) {
                    $screenshot->activity = ScreenshotActivity::Idle;
                }
            });
    }

    /**
     * Compare the clock visible on screen with the capture time of the file.
     *
     * @return array{minutes: int, screen_clock: string, file_time: string, source: string}|null
     */
    protected function clockMismatch(Screenshot $screenshot): ?array
    {
        if ($screenshot->screen_clock === null || $screenshot->taken_at === null || $screenshot->taken_at_source === TimestampSource::Unknown) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $screenshot->screen_clock));
        $difference = ($hour * 60 + $minute) - ($screenshot->taken_at->hour * 60 + $screenshot->taken_at->minute);
        $wrap = fn (int $minutes, int $period) => (($minutes % $period) + $period + intdiv($period, 2)) % $period - intdiv($period, 2);

        // A clock without AM/PM may be a 12-hour clock, so 08:15 on screen also matches 20:15.
        $minutes = $screenshot->screen_clock_ambiguous ? $wrap($difference, 720) : $wrap($difference, 1440);

        if (abs($minutes) <= config('scanning.clock_mismatch_minutes')) {
            return null;
        }

        return [
            'minutes' => $minutes,
            'screen_clock' => $screenshot->screen_clock,
            'file_time' => $screenshot->taken_at->format('H:i'),
            'source' => $screenshot->taken_at_source->value,
        ];
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
