<?php

namespace App\Http\Controllers\Scans;

use App\Enums\FindingType;
use App\Enums\ScanStatus;
use App\Enums\ScreenshotActivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scans\StoreScanRequest;
use App\Models\Scan;
use App\Models\ScanFinding;
use App\Models\Screenshot;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    /**
     * Display the team's scans.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $scans = $current_team->scans()
            ->with('employee:id,name')
            ->withCount(['screenshots', 'findings'])
            ->latest()
            ->paginate(20)
            ->through(fn (Scan $scan) => [
                'id' => $scan->id,
                'employee' => $scan->employee->name,
                'workDate' => $scan->work_date?->toDateString(),
                'status' => $scan->status->value,
                'statusLabel' => $scan->status->label(),
                'screenshotsCount' => $scan->screenshots_count,
                'findingsCount' => $scan->findings_count,
                'createdAt' => $scan->created_at?->toIso8601String(),
            ]);

        return Inertia::render('scans/Index', [
            'scans' => $scans,
        ]);
    }

    /**
     * Show the upload form.
     */
    public function create(Team $current_team): Response
    {
        return Inertia::render('scans/Create', [
            'employees' => $current_team->employees()->orderBy('name')->pluck('name'),
            'limits' => [
                'maxScreenshots' => config('scanning.max_screenshots_per_scan'),
                'maxFileSizeKb' => config('scanning.max_file_size_kb'),
                'filesPerRequest' => config('scanning.files_per_upload_request'),
            ],
        ]);
    }

    /**
     * Create a scan for an employee so screenshots can be uploaded to it.
     */
    public function store(StoreScanRequest $request, Team $current_team): JsonResponse
    {
        $employee = $current_team->employees()->firstOrCreate([
            'name' => trim($request->validated('employee_name')),
        ]);

        $scan = $current_team->scans()->create([
            'employee_id' => $employee->id,
            'user_id' => $request->user()->id,
            'work_date' => $request->validated('work_date'),
            'status' => ScanStatus::Uploading,
        ]);

        return response()->json(['id' => $scan->id], 201);
    }

    /**
     * Show the scan's analysis results.
     */
    public function show(Team $current_team, Scan $scan): Response
    {
        $scan->load('employee:id,name')->loadCount('screenshots');

        return Inertia::render('scans/Show', [
            'scan' => [
                'id' => $scan->id,
                'employee' => $scan->employee->name,
                'workDate' => $scan->work_date?->toDateString(),
                'status' => $scan->status->value,
                'statusLabel' => $scan->status->label(),
                'isRunning' => $scan->status->isRunning(),
                'processedCount' => $scan->processed_count,
                'screenshotsCount' => $scan->screenshots_count,
                'error' => $scan->error,
                'analyzedAt' => $scan->analyzed_at?->toIso8601String(),
            ],
            'screenshots' => fn () => $scan->status === ScanStatus::Completed ? $this->screenshots($scan) : [],
            'findings' => fn () => $scan->status === ScanStatus::Completed ? $this->findings($scan) : [],
            'labels' => [
                'activity' => collect(ScreenshotActivity::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]),
                'finding' => collect(FindingType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]),
            ],
        ]);
    }

    /**
     * Delete the scan together with its files.
     */
    public function destroy(Team $current_team, Scan $scan): RedirectResponse
    {
        abort_if($scan->status->isRunning(), 409, 'Scan sedang dianalisis.');

        Storage::disk('local')->deleteDirectory($scan->storageDirectory());

        $scan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scan dihapus.']);

        return to_route('scans.index', ['current_team' => $current_team->slug]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function screenshots(Scan $scan): array
    {
        return $scan->screenshots()
            ->chronological()
            ->get()
            ->map(fn (Screenshot $screenshot) => [
                'id' => $screenshot->id,
                'name' => $screenshot->original_name,
                'takenAt' => $screenshot->taken_at?->format('Y-m-d H:i:s'),
                'takenAtSource' => $screenshot->taken_at_source->value,
                'activity' => ($screenshot->activity ?? ScreenshotActivity::Unknown)->value,
                'changeRatio' => $screenshot->change_ratio === null ? null : (float) $screenshot->change_ratio,
                'changeBbox' => $screenshot->change_bbox,
                'changeRegions' => collect($screenshot->change_regions ?? [])->pluck('bbox')->all(),
                'screenClock' => $screenshot->screen_clock,
                'similarityGroup' => $screenshot->similarity_group,
                'hasThumbnail' => $screenshot->thumbnail_path !== null,
                'error' => $screenshot->error,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function findings(Scan $scan): array
    {
        return $scan->findings()
            ->with(['relatedScreenshot:id,scan_id,original_name,taken_at', 'relatedScreenshot.scan:id,work_date,created_at'])
            ->orderBy('screenshot_id')
            ->get()
            ->map(fn (ScanFinding $finding) => [
                'id' => $finding->id,
                'type' => $finding->type->value,
                'screenshotId' => $finding->screenshot_id,
                'score' => $finding->score === null ? null : (float) $finding->score,
                'details' => $finding->details,
                'description' => $finding->description(),
                'related' => $finding->relatedScreenshot ? [
                    'id' => $finding->relatedScreenshot->id,
                    'scanId' => $finding->relatedScreenshot->scan_id,
                    'name' => $finding->relatedScreenshot->original_name,
                    'takenAt' => $finding->relatedScreenshot->taken_at?->format('Y-m-d H:i:s'),
                    'scanDate' => $finding->relatedScreenshot->scan->work_date?->toDateString()
                        ?? $finding->relatedScreenshot->scan->created_at?->toDateString(),
                ] : null,
            ])
            ->values()
            ->all();
    }
}
