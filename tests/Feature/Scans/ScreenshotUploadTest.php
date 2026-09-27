<?php

use App\Enums\TimestampSource;
use App\Models\Employee;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->scan = Scan::factory()->for(Employee::factory()->for($this->team))->create();
});

test('uploaded screenshots are stored with their capture time', function () {
    $this->actingAs($this->user)
        ->post(route('scans.screenshots.store', [$this->team, $this->scan]), [
            'files' => [
                UploadedFile::fake()->image('SS_20260927_081500.png'),
                UploadedFile::fake()->image('capture.jpg'),
                UploadedFile::fake()->image('capture.jpeg'),
            ],
            'modified_at' => [null, '2026-09-27T09:30:00'],
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJson(['uploaded' => 3, 'total' => 3]);

    $screenshots = $this->scan->screenshots()->orderBy('id')->get();

    expect($screenshots[0]->taken_at->toDateTimeString())->toBe('2026-09-27 08:15:00')
        ->and($screenshots[0]->taken_at_source)->toBe(TimestampSource::Filename)
        ->and($screenshots[1]->taken_at->toDateTimeString())->toBe('2026-09-27 09:30:00')
        ->and($screenshots[1]->taken_at_source)->toBe(TimestampSource::FileModified)
        ->and($screenshots[2]->taken_at)->toBeNull()
        ->and($screenshots[2]->taken_at_source)->toBe(TimestampSource::Unknown);

    Storage::disk('local')->assertExists($screenshots[0]->path);
});

test('only jpg, jpeg and png files are accepted', function () {
    $this->actingAs($this->user)
        ->postJson(route('scans.screenshots.store', [$this->team, $this->scan]), [
            'files' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0' => 'File files.0 harus berformat JPG, JPEG, atau PNG.']);

    expect($this->scan->screenshots()->count())->toBe(0);
});

test('screenshots cannot be added after the analysis started', function () {
    $scan = Scan::factory()->completed()->for(Employee::factory()->for($this->team))->create();

    $this->actingAs($this->user)
        ->postJson(route('scans.screenshots.store', [$this->team, $scan]), [
            'files' => [UploadedFile::fake()->image('a.png')],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files' => 'Scan ini sudah dianalisis, buat scan baru untuk menambah screenshot.']);
});

test('a scan cannot hold more screenshots than the limit', function () {
    config(['scanning.max_screenshots_per_scan' => 2]);

    $this->actingAs($this->user)
        ->postJson(route('scans.screenshots.store', [$this->team, $this->scan]), [
            'files' => [
                UploadedFile::fake()->image('a.png'),
                UploadedFile::fake()->image('b.png'),
                UploadedFile::fake()->image('c.png'),
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files' => 'Maksimal 2 screenshot per scan.']);
});

test('users cannot upload to another team\'s scan', function () {
    $otherScan = Scan::factory()->create();

    $this->actingAs($this->user)
        ->postJson(route('scans.screenshots.store', [$this->team, $otherScan]), [
            'files' => [UploadedFile::fake()->image('a.png')],
        ])
        ->assertNotFound();
});
