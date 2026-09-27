<?php

use App\Enums\FindingType;
use App\Enums\ScanStatus;
use App\Enums\ScreenshotActivity;
use App\Jobs\CompareScanScreenshots;
use App\Jobs\ExtractScreenshotFeatures;
use App\Models\Employee;
use App\Models\Scan;
use App\Models\ScanFinding;
use App\Models\Screenshot;
use App\Models\User;
use Illuminate\Bus\PendingBatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->employee = Employee::factory()->for($this->team)->create();
    $this->scan = Scan::factory()->for($this->employee)->create();
});

test('starting the analysis queues feature extraction in chunks', function () {
    Bus::fake();
    config(['scanning.extract_chunk_size' => 2]);
    Screenshot::factory()->count(3)->for($this->scan)->create();

    $this->actingAs($this->user)
        ->post(route('scans.analysis.store', [$this->team, $this->scan]))
        ->assertRedirect(route('scans.show', [$this->team, $this->scan]));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2
        && $batch->jobs->every(fn ($job) => $job instanceof ExtractScreenshotFeatures));

    expect($this->scan->fresh()->status)->toBe(ScanStatus::Queued);
});

test('the analysis cannot start without screenshots', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->post(route('scans.analysis.store', [$this->team, $this->scan]))
        ->assertSessionHasErrors(['scan' => 'Belum ada screenshot yang diunggah.']);

    Bus::assertNothingBatched();
});

test('the analysis cannot start twice at the same time', function () {
    Bus::fake();
    $scan = Scan::factory()->processing()->for($this->employee)->create();
    Screenshot::factory()->for($scan)->create();

    $this->actingAs($this->user)
        ->post(route('scans.analysis.store', [$this->team, $scan]))
        ->assertSessionHasErrors(['scan' => 'Scan ini sedang dianalisis.']);

    Bus::assertNothingBatched();
});

test('analyzer results become activity levels and findings', function () {
    config([
        'scanning.idle_change_ratio' => 0.003,
        'scanning.low_change_ratio' => 0.015,
        'scanning.time_gap_minutes' => 15,
    ]);

    $at = fn (string $time) => ['taken_at' => "2026-09-27 {$time}", 'original_name' => "{$time}.png"];
    $first = Screenshot::factory()->for($this->scan)->create($at('08:00:00'));
    $idle = Screenshot::factory()->for($this->scan)->create($at('08:05:00'));
    $low = Screenshot::factory()->for($this->scan)->create($at('08:10:00'));
    $afterGap = Screenshot::factory()->for($this->scan)->create($at('08:40:00'));
    $copy = Screenshot::factory()->for($this->scan)->create($at('08:45:00'));

    $oldScan = Scan::factory()->completed()->for($this->employee)->create();
    $oldScreenshot = Screenshot::factory()->extracted()->for($oldScan)->create();
    $otherEmployeesScreenshot = Screenshot::factory()->extracted()->create();

    Http::preventStrayRequests();
    Http::fake([
        '*/extract' => fn (Request $request) => Http::response(['results' => collect($request['items'])->map(fn ($item) => [
            'id' => $item['id'],
            'md5' => md5((string) $item['id']),
            'phash' => str_pad(dechex($item['id']), 16, '0', STR_PAD_LEFT),
            'width' => 1920,
            'height' => 1080,
            'file_size' => 1000,
            'error' => null,
        ])->all()]),
        '*/compare' => Http::response([
            'changes' => [
                ['id' => $idle->id, 'previous_id' => $first->id, 'change_ratio' => 0.001, 'bbox' => [0.9, 0.95, 0.95, 1.0]],
                ['id' => $low->id, 'previous_id' => $idle->id, 'change_ratio' => 0.01, 'bbox' => [0.1, 0.1, 0.5, 0.2]],
                ['id' => $afterGap->id, 'previous_id' => $low->id, 'change_ratio' => 0.4, 'bbox' => [0, 0, 1, 1]],
                ['id' => $copy->id, 'previous_id' => $afterGap->id, 'change_ratio' => 0.3, 'bbox' => [0, 0, 1, 1]],
            ],
            'duplicates' => [['id' => $copy->id, 'original_id' => $first->id]],
            'recycled' => [['id' => $afterGap->id, 'reference_id' => $oldScreenshot->id, 'distance' => 0, 'exact' => true]],
            'groups' => [[$first->id, $idle->id]],
        ]),
    ]);

    $this->actingAs($this->user)
        ->post(route('scans.analysis.store', [$this->team, $this->scan]))
        ->assertRedirect();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/compare')
        && collect($request['screenshots'])->pluck('id')->all() === [$first->id, $idle->id, $low->id, $afterGap->id, $copy->id]
        && collect($request['references'])->pluck('id')->all() === [$oldScreenshot->id]);

    $scan = $this->scan->fresh();
    expect($scan->status)->toBe(ScanStatus::Completed)
        ->and($scan->processed_count)->toBe(5)
        ->and($first->fresh()->activity)->toBe(ScreenshotActivity::Unknown)
        ->and($idle->fresh()->activity)->toBe(ScreenshotActivity::Idle)
        ->and($low->fresh()->activity)->toBe(ScreenshotActivity::Low)
        ->and($afterGap->fresh()->activity)->toBe(ScreenshotActivity::Duplicate)
        ->and($copy->fresh()->activity)->toBe(ScreenshotActivity::Duplicate)
        ->and($idle->fresh()->similarity_group)->toBe(1)
        ->and($idle->fresh()->thumbnail_path)->toBe("screenshots/{$scan->id}/thumbs/{$idle->id}.jpg");

    $findings = ScanFinding::where('scan_id', $scan->id)->get()
        ->map(fn (ScanFinding $finding) => [$finding->type, $finding->screenshot_id, $finding->related_screenshot_id])
        ->sortBy(fn ($row) => $row[0]->value.$row[1])
        ->values()
        ->all();

    expect($findings)->toEqual(collect([
        [FindingType::ExactDuplicate, $copy->id, $first->id],
        [FindingType::Idle, $idle->id, $first->id],
        [FindingType::Recycled, $afterGap->id, $oldScreenshot->id],
        [FindingType::TimeGap, $afterGap->id, $low->id],
    ])->sortBy(fn ($row) => $row[0]->value.$row[1])->values()->all());

    expect(ScanFinding::where('type', FindingType::TimeGap)->sole()->details['minutes'])->toBe(30);
    expect($otherEmployeesScreenshot->scan->employee_id)->not->toBe($this->employee->id);
});

test('the scan is marked as failed when the comparison cannot finish', function () {
    Screenshot::factory()->extracted()->for($this->scan)->create();
    Http::fake(['*' => Http::failedConnection()]);
    $job = new CompareScanScreenshots($this->scan);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(ConnectionException::class);

    $job->failed(new ConnectionException('Connection refused'));

    $scan = $this->scan->fresh();
    expect($scan->status)->toBe(ScanStatus::Failed)
        ->and($scan->error)->toBe('Analisis gagal: Connection refused');
});
