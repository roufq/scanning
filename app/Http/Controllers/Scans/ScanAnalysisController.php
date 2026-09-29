<?php

namespace App\Http\Controllers\Scans;

use App\Actions\Scans\EnsureQueueWorker;
use App\Actions\Scans\StartScanAnalysis;
use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ScanAnalysisController extends Controller
{
    /**
     * Start (or restart) the analysis of a scan.
     */
    public function store(Team $current_team, Scan $scan, StartScanAnalysis $startAnalysis, EnsureQueueWorker $ensureWorker): RedirectResponse
    {
        if ($scan->status->isRunning()) {
            throw ValidationException::withMessages(['scan' => 'Scan ini sedang dianalisis.']);
        }

        if (! $scan->screenshots()->exists()) {
            throw ValidationException::withMessages(['scan' => 'Belum ada screenshot yang diunggah.']);
        }

        $startAnalysis->handle($scan);
        $ensureWorker->handle();

        return to_route('scans.show', ['current_team' => $current_team->slug, 'scan' => $scan->id]);
    }
}
