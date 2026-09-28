<?php

namespace App\Http\Controllers\Scans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scans\SaveScanCategoryRequest;
use App\Models\ScanCategory;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScanCategoryController extends Controller
{
    /**
     * Add an application category.
     */
    public function store(SaveScanCategoryRequest $request, Team $current_team): RedirectResponse
    {
        $current_team->scanCategories()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori ditambahkan.']);

        return back();
    }

    /**
     * Update an application category.
     */
    public function update(SaveScanCategoryRequest $request, Team $current_team, ScanCategory $scan_category): RedirectResponse
    {
        $scan_category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori disimpan.']);

        return back();
    }

    /**
     * Delete an application category.
     */
    public function destroy(Request $request, Team $current_team, ScanCategory $scan_category): RedirectResponse
    {
        abort_unless($request->user()->can('update', $current_team), 403);

        $scan_category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori dihapus.']);

        return back();
    }
}
