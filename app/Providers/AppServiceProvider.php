<?php

namespace App\Providers;

use App\Actions\Scans\EnsureQueueWorker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->reportQueueWorkerHeartbeat();

        DevCommands::artisan('analyzer:serve', 'analyzer');
    }

    /**
     * Let running queue workers tell the web app they are alive (see EnsureQueueWorker).
     */
    protected function reportQueueWorkerHeartbeat(): void
    {
        $beat = fn () => Cache::put(EnsureQueueWorker::HEARTBEAT_KEY, now()->getTimestamp(), 60);

        Queue::looping($beat);
        Queue::before(fn () => Cache::put(EnsureQueueWorker::BUSY_KEY, true, 960));
        Queue::after(function () use ($beat) {
            Cache::forget(EnsureQueueWorker::BUSY_KEY);
            $beat();
        });
        Queue::failing(fn () => Cache::forget(EnsureQueueWorker::BUSY_KEY));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
