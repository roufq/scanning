<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Scans\ScanAnalysisController;
use App\Http\Controllers\Scans\ScanController;
use App\Http\Controllers\Scans\ScanScreenshotController;
use App\Http\Controllers\Scans\ScreenshotImageController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::scopeBindings()->group(function () {
            Route::resource('scans', ScanController::class)->except(['edit', 'update']);
            Route::post('scans/{scan}/screenshots', [ScanScreenshotController::class, 'store'])->name('scans.screenshots.store');
            Route::post('scans/{scan}/analysis', [ScanAnalysisController::class, 'store'])->name('scans.analysis.store');
            Route::get('scans/{scan}/screenshots/{screenshot}/{variant}', ScreenshotImageController::class)
                ->whereIn('variant', ['image', 'thumbnail'])
                ->name('scans.screenshots.image');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
