<?php

use App\Actions\Scans\ApplyScanComparison;
use App\Enums\FindingType;
use App\Enums\ScreenshotActivity;
use App\Enums\TimestampSource;
use App\Models\Scan;
use App\Models\ScanCategory;
use App\Models\ScanFinding;
use App\Models\ScanSetting;
use App\Models\Screenshot;

beforeEach(function () {
    config([
        'scanning.idle_change_ratio' => 0.003,
        'scanning.low_change_ratio' => 0.015,
        'scanning.clock_mismatch_minutes' => 5,
        'scanning.cursor_max_regions' => 2,
        'scanning.cursor_max_region_size' => 0.04,
        'scanning.jiggler_min_streak' => 2,
        'scanning.time_gap_minutes' => 60,
    ]);

    $this->scan = Scan::factory()->create();
});

/**
 * @param  list<string>  $times
 * @return list<Screenshot>
 */
function screenshotsAt(Scan $scan, array $times, array $attributes = []): array
{
    return array_map(fn (string $time) => Screenshot::factory()->extracted()->for($scan)->create([
        'taken_at' => "2026-09-27 {$time}",
        'original_name' => "{$time}.png",
        ...$attributes,
    ]), $times);
}

function change(Screenshot $screenshot, Screenshot $previous, float $ratio, array $regions): array
{
    return [
        'id' => $screenshot->id,
        'previous_id' => $previous->id,
        'change_ratio' => $ratio,
        'bbox' => $regions[0]['bbox'] ?? null,
        'regions' => $regions,
    ];
}

function applyChanges(Scan $scan, array $changes): void
{
    (new ApplyScanComparison)->handle($scan, ['changes' => $changes, 'duplicates' => [], 'recycled' => [], 'groups' => []]);
}

$cursor = fn (float $x) => ['bbox' => [$x, 0.5, $x + 0.01, 0.52], 'ratio' => 0.0002];

test('a streak of cursor-only changes is reported as a mouse jiggler', function () use ($cursor) {
    [$a, $b, $c, $d] = screenshotsAt($this->scan, ['08:00:00', '08:05:00', '08:10:00', '08:15:00']);

    applyChanges($this->scan, [
        change($b, $a, 0.0004, [$cursor(0.2), $cursor(0.6)]),
        change($c, $b, 0.0004, [$cursor(0.6), $cursor(0.3)]),
        change($d, $c, 0.2, [['bbox' => [0, 0, 1, 1], 'ratio' => 0.2]]),
    ]);

    expect($b->fresh()->activity)->toBe(ScreenshotActivity::CursorOnly)
        ->and($c->fresh()->activity)->toBe(ScreenshotActivity::CursorOnly)
        ->and($d->fresh()->activity)->toBe(ScreenshotActivity::Active)
        ->and(ScanFinding::where('type', FindingType::MouseJiggler)->pluck('screenshot_id')->all())->toBe([$b->id, $c->id])
        ->and(ScanFinding::where('type', FindingType::Idle)->count())->toBe(0);
});

test('a single cursor-only change counts as an idle screen', function () use ($cursor) {
    [$a, $b, $c] = screenshotsAt($this->scan, ['08:00:00', '08:05:00', '08:10:00']);

    applyChanges($this->scan, [
        change($b, $a, 0.0004, [$cursor(0.2)]),
        change($c, $b, 0.2, [['bbox' => [0, 0, 1, 1], 'ratio' => 0.2]]),
    ]);

    expect($b->fresh()->activity)->toBe(ScreenshotActivity::Idle)
        ->and(ScanFinding::where('type', FindingType::Idle)->sole()->screenshot_id)->toBe($b->id)
        ->and(ScanFinding::where('type', FindingType::MouseJiggler)->count())->toBe(0);
});

test('small changes spread over large areas are not treated as cursor movement', function () {
    [$a, $b] = screenshotsAt($this->scan, ['08:00:00', '08:05:00']);

    applyChanges($this->scan, [
        change($b, $a, 0.01, [['bbox' => [0.1, 0.1, 0.6, 0.15], 'ratio' => 0.01]]),
    ]);

    expect($b->fresh()->activity)->toBe(ScreenshotActivity::Low);
});

test('a screen clock that differs from the capture time is reported', function (string $clock, bool $ambiguous, TimestampSource $source, ?int $minutes) {
    [$screenshot] = screenshotsAt($this->scan, ['08:15:00'], [
        'screen_clock' => $clock,
        'screen_clock_ambiguous' => $ambiguous,
        'taken_at_source' => $source,
    ]);

    applyChanges($this->scan, []);

    $finding = ScanFinding::where('type', FindingType::ClockMismatch)->first();

    expect($finding?->details['minutes'])->toBe($minutes);

    if ($finding) {
        expect($finding->screenshot_id)->toBe($screenshot->id)
            ->and($finding->description())->toBe("Jam di layar {$clock}, tetapi waktu di nama file 08:15 (selisih ".abs($minutes).' menit).');
    }
})->with([
    'matching clock' => ['08:17', false, TimestampSource::Filename, null],
    'clock ahead' => ['09:20', false, TimestampSource::Filename, 65],
    'clock behind' => ['07:40', false, TimestampSource::Filename, -35],
    'across midnight' => ['23:50', false, TimestampSource::Filename, -505],
    '12-hour clock without AM/PM' => ['08:14', true, TimestampSource::Filename, null],
    'unknown capture time' => ['11:00', false, TimestampSource::Unknown, null],
]);

test('duplicated screenshots are not also reported as idle', function () {
    [$a, $b] = screenshotsAt($this->scan, ['08:00:00', '08:05:00']);

    (new ApplyScanComparison)->handle($this->scan, [
        'changes' => [change($b, $a, 0.0, [])],
        'duplicates' => [['id' => $b->id, 'original_id' => $a->id]],
        'recycled' => [],
        'groups' => [],
    ]);

    expect($b->fresh()->activity)->toBe(ScreenshotActivity::Duplicate)
        ->and(ScanFinding::pluck('type')->all())->toBe([FindingType::ExactDuplicate]);
});

test('a keyword in the window title decides the category before the AI', function () {
    $youtube = ScanCategory::factory()->nonProductive()->for($this->scan->team)->create(['name' => 'Video', 'keywords' => ['youtube']]);
    $spreadsheet = ScanCategory::factory()->for($this->scan->team)->create(['name' => 'Spreadsheet', 'keywords' => ['excel']]);
    [$screenshot] = screenshotsAt($this->scan, ['08:00:00'], [
        'window_title' => 'Lagu santai - YouTube - Google Chrome',
        'category_scores' => [['id' => $spreadsheet->id, 'score' => 0.9]],
    ]);

    applyChanges($this->scan, []);

    $screenshot->refresh();
    $finding = ScanFinding::where('type', FindingType::NonWork)->sole();

    expect($screenshot->category_id)->toBe($youtube->id)
        ->and($screenshot->category_source)->toBe('keyword')
        ->and($screenshot->category_keyword)->toBe('youtube')
        ->and($finding->screenshot_id)->toBe($screenshot->id)
        ->and($finding->description())->toBe('Judul jendela/tab memuat "youtube" → kategori Video.');
});

test('keywords only match whole words', function () {
    ScanCategory::factory()->nonProductive()->for($this->scan->team)->create(['keywords' => ['game']]);
    [$screenshot] = screenshotsAt($this->scan, ['08:00:00'], ['window_title' => 'Gameplan Q4.xlsx - Excel']);

    applyChanges($this->scan, []);

    expect($screenshot->fresh()->category_id)->toBeNull();
});

test('a confident AI guess for a non-work category is reported', function (float $score, bool $reported) {
    $game = ScanCategory::factory()->nonProductive()->for($this->scan->team)->create(['name' => 'Game']);
    config(['scanning.ai_min_confidence' => 0.5]);
    [$screenshot] = screenshotsAt($this->scan, ['08:00:00'], [
        'category_scores' => [['id' => $game->id, 'score' => $score]],
    ]);

    applyChanges($this->scan, []);

    expect($screenshot->fresh()->category_id)->toBe($reported ? $game->id : null)
        ->and(ScanFinding::where('type', FindingType::NonWork)->exists())->toBe($reported);

    if ($reported) {
        expect(ScanFinding::sole()->description())->toBe('AI mengenali layar sebagai Game (keyakinan 81,0%).');
    }
})->with([
    'confident' => [0.81, true],
    'unsure' => [0.42, false],
]);

test('work categories and disabled categories never become findings', function () {
    $excel = ScanCategory::factory()->for($this->scan->team)->create();
    $disabledGame = ScanCategory::factory()->nonProductive()->disabled()->for($this->scan->team)->create(['keywords' => ['steam']]);
    [$work, $disabled] = screenshotsAt($this->scan, ['08:00:00', '08:05:00']);
    $work->update(['category_scores' => [['id' => $excel->id, 'score' => 0.95]]]);
    $disabled->update(['window_title' => 'Steam', 'category_scores' => [['id' => $disabledGame->id, 'score' => 0.99]]]);

    applyChanges($this->scan, []);

    expect($work->fresh()->category_id)->toBe($excel->id)
        ->and($disabled->fresh()->category_id)->toBeNull()
        ->and(ScanFinding::where('type', FindingType::NonWork)->count())->toBe(0);
});

test('team settings override the configured thresholds', function () {
    ScanSetting::create(['team_id' => $this->scan->team_id, 'values' => ['time_gap_minutes' => 4]]);
    screenshotsAt($this->scan, ['08:00:00', '08:05:00']);

    applyChanges($this->scan, []);

    expect(ScanFinding::where('type', FindingType::TimeGap)->count())->toBe(1);
});
