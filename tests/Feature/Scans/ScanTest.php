<?php

use App\Enums\ScanStatus;
use App\Models\Employee;
use App\Models\Scan;
use App\Models\Screenshot;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();

    $this->get(route('scans.index', $user->currentTeam))->assertRedirect(route('login'));
});

test('users only see scans of their current team', function () {
    $user = User::factory()->create();
    $ownScan = Scan::factory()->for(Employee::factory()->for($user->currentTeam))->create();
    Scan::factory()->create();

    $this->actingAs($user)
        ->get(route('scans.index', $user->currentTeam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scans/Index')
            ->has('scans.data', 1)
            ->where('scans.data.0.id', $ownScan->id)
        );
});

test('users cannot open another team\'s scan', function () {
    $user = User::factory()->create();
    $otherScan = Scan::factory()->create();

    $this->actingAs($user)
        ->get(route('scans.show', [$user->currentTeam, $otherScan]))
        ->assertNotFound();
});

test('the upload form lists the team\'s employees', function () {
    $user = User::factory()->create();
    Employee::factory()->for($user->currentTeam)->create(['name' => 'Budi']);
    Employee::factory()->create(['name' => 'Other Team Employee']);

    $this->actingAs($user)
        ->get(route('scans.create', $user->currentTeam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scans/Create')
            ->where('employees', ['Budi'])
        );
});

test('a scan is created for a new employee', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('scans.store', $user->currentTeam), [
            'employee_name' => '  Budi Santoso ',
            'work_date' => '2026-09-27',
        ]);

    $scan = Scan::sole();

    $response->assertCreated()->assertJson(['id' => $scan->id]);
    expect($scan->employee->name)->toBe('Budi Santoso')
        ->and($scan->team_id)->toBe($user->currentTeam->id)
        ->and($scan->user_id)->toBe($user->id)
        ->and($scan->status)->toBe(ScanStatus::Uploading)
        ->and($scan->work_date->toDateString())->toBe('2026-09-27');
});

test('a scan reuses an existing employee of the team', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->for($user->currentTeam)->create(['name' => 'Budi']);

    $this->actingAs($user)
        ->postJson(route('scans.store', $user->currentTeam), ['employee_name' => 'Budi'])
        ->assertCreated();

    expect(Employee::count())->toBe(1)
        ->and(Scan::sole()->employee_id)->toBe($employee->id);
});

test('the employee name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('scans.store', $user->currentTeam), ['employee_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_name' => 'The nama karyawan field is required.']);
});

test('a scan and its files can be deleted', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $scan = Scan::factory()->completed()->for(Employee::factory()->for($user->currentTeam))->create();
    $screenshot = Screenshot::factory()->for($scan)->create();
    Storage::disk('local')->put($screenshot->path, 'image');

    $this->actingAs($user)
        ->delete(route('scans.destroy', [$user->currentTeam, $scan]))
        ->assertRedirect(route('scans.index', $user->currentTeam));

    $this->assertModelMissing($scan);
    $this->assertModelMissing($screenshot);
    Storage::disk('local')->assertMissing($screenshot->path);
});

test('a scan cannot be deleted while it is being analyzed', function () {
    $user = User::factory()->create();
    $scan = Scan::factory()->processing()->for(Employee::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->delete(route('scans.destroy', [$user->currentTeam, $scan]))
        ->assertConflict();

    $this->assertModelExists($scan);
});

test('screenshot images are only served within the scan they belong to', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $scan = Scan::factory()->for(Employee::factory()->for($team))->create();
    $otherScan = Scan::factory()->for(Employee::factory()->for($team))->create();
    $screenshot = Screenshot::factory()->for($scan)->create();
    Storage::disk('local')->put($screenshot->path, 'image-bytes');

    $this->actingAs($user)
        ->get(route('scans.screenshots.image', [$team, $scan, $screenshot, 'image']))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('scans.screenshots.image', [$team, $otherScan, $screenshot, 'image']))
        ->assertNotFound();
});
