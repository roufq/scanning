<?php

use App\Enums\FindingType;
use App\Exports\ScanExport;
use App\Models\Employee;
use App\Models\Scan;
use App\Models\Screenshot;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->scan = Scan::factory()->completed()
        ->for(Employee::factory()->for($this->team)->state(['name' => 'Budi Santoso']))
        ->create(['work_date' => '2026-09-27']);

    $first = Screenshot::factory()->extracted()->for($this->scan)->create(['taken_at' => '2026-09-27 08:00:00', 'thumbnail_path' => 'thumbs/1.jpg']);
    $second = Screenshot::factory()->extracted()->for($this->scan)->create(['taken_at' => '2026-09-27 08:05:00', 'thumbnail_path' => 'thumbs/2.jpg']);
    Storage::disk('local')->put('thumbs/1.jpg', 'jpeg');
    Storage::disk('local')->put('thumbs/2.jpg', 'jpeg');

    $this->scan->findings()->create([
        'screenshot_id' => $second->id,
        'related_screenshot_id' => $first->id,
        'type' => FindingType::Idle,
        'score' => 0.001,
        'details' => ['change_ratio' => 0.001],
    ]);
});

test('the scan report can be downloaded as excel', function () {
    Excel::fake();

    $this->actingAs($this->user)
        ->get(route('scans.export', [$this->team, $this->scan, 'xlsx']))
        ->assertOk();

    Excel::assertDownloaded('scan-budi-santoso-2026-09-27.xlsx', function (ScanExport $export) {
        [$summary, $findings, $screenshots] = $export->sheets();

        return $summary->array()[0] === ['Karyawan', 'Budi Santoso']
            && $findings->array() === [['08:05', 'Layar diam', 'Layar hanya berubah 0,10% dibanding screenshot 08:00.', $this->scan->screenshots()->orderBy('id')->get()[1]->original_name, $this->scan->screenshots()->orderBy('id')->first()->original_name]]
            && count($screenshots->array()) === 2;
    });
});

test('the scan report can be downloaded as pdf', function () {
    $response = $this->actingAs($this->user)
        ->get(route('scans.export', [$this->team, $this->scan, 'pdf']))
        ->assertOk()
        ->assertDownload('scan-budi-santoso-2026-09-27.pdf');

    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('reports are only available once the analysis is completed', function () {
    $scan = Scan::factory()->for(Employee::factory()->for($this->team))->create();

    $this->actingAs($this->user)
        ->get(route('scans.export', [$this->team, $scan, 'xlsx']))
        ->assertNotFound();
});

test('users cannot export another team\'s scan', function () {
    $otherScan = Scan::factory()->completed()->create();

    $this->actingAs($this->user)
        ->get(route('scans.export', [$this->team, $otherScan, 'pdf']))
        ->assertNotFound();
});
