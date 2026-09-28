<?php

namespace App\Http\Controllers\Scans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scans\UpdateScanSettingsRequest;
use App\Models\ScanCategory;
use App\Models\ScanSetting;
use App\Models\Team;
use App\Services\ScreenshotAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScanSettingsController extends Controller
{
    /**
     * Show the analysis settings and application categories.
     */
    public function edit(Request $request, Team $current_team, ScreenshotAnalyzer $analyzer): Response
    {
        ScanCategory::ensureDefaultsFor($current_team);

        $editable = array_keys(ScanSetting::EDITABLE);

        return Inertia::render('scans/Settings', [
            'settings' => array_intersect_key(ScanSetting::valuesFor($current_team->id), array_flip($editable)),
            'defaults' => array_intersect_key(config('scanning'), array_flip($editable)),
            'categories' => $current_team->scanCategories()
                ->orderByDesc('is_productive')
                ->orderBy('name')
                ->get()
                ->map(fn (ScanCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'prompt' => $category->prompt,
                    'keywords' => $category->keywords ?? [],
                    'isProductive' => $category->is_productive,
                    'isEnabled' => $category->is_enabled,
                ]),
            'canEdit' => $request->user()->can('update', $current_team),
            'analyzer' => Inertia::defer(fn () => $analyzer->health()),
        ]);
    }

    /**
     * Save the team's analysis thresholds.
     */
    public function update(UpdateScanSettingsRequest $request, Team $current_team): RedirectResponse
    {
        $current_team->scanSetting()->updateOrCreate([], ['values' => $request->validated()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengaturan scan disimpan.']);

        return back();
    }
}
