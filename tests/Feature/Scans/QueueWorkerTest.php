<?php

use App\Actions\Scans\EnsureQueueWorker;
use App\Enums\ScanStatus;
use App\Models\Employee;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

test('a worker counts as alive while its heartbeat is recent or it is busy', function () {
    $ensureWorker = new EnsureQueueWorker;

    expect($ensureWorker->workerIsAlive())->toBeFalse();

    Cache::put(EnsureQueueWorker::HEARTBEAT_KEY, now()->subSeconds(5)->getTimestamp());
    expect($ensureWorker->workerIsAlive())->toBeTrue();

    Cache::put(EnsureQueueWorker::HEARTBEAT_KEY, now()->subMinutes(5)->getTimestamp());
    expect($ensureWorker->workerIsAlive())->toBeFalse();

    Cache::put(EnsureQueueWorker::BUSY_KEY, true);
    expect($ensureWorker->workerIsAlive())->toBeTrue();
});

test('running queue workers report their heartbeat', function () {
    event(new Looping('database', 'default'));

    expect((new EnsureQueueWorker)->workerIsAlive())->toBeTrue();
});

test('a scan waiting in the queue without a worker is shown as stalled', function (bool $workerRunning, bool $stalled) {
    $user = User::factory()->create();
    $scan = Scan::factory()->for(Employee::factory()->for($user->currentTeam))->create(['status' => ScanStatus::Queued]);
    Scan::whereKey($scan->id)->update(['updated_at' => now()->subMinutes(3)]);

    if ($workerRunning) {
        Cache::put(EnsureQueueWorker::HEARTBEAT_KEY, now()->getTimestamp());
    }

    $this->actingAs($user)
        ->get(route('scans.show', [$user->currentTeam, $scan]))
        ->assertInertia(fn (Assert $page) => $page->where('scan.isStalled', $stalled));
})->with([
    'no worker' => [false, true],
    'worker running' => [true, false],
]);
