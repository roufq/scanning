<?php

namespace App\Http\Controllers\Scans;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\Screenshot;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenshotImageController extends Controller
{
    /**
     * Stream a screenshot (or its thumbnail) from private storage.
     */
    public function __invoke(Team $current_team, Scan $scan, Screenshot $screenshot, string $variant): StreamedResponse
    {
        $path = $variant === 'thumbnail' && $screenshot->thumbnail_path
            ? $screenshot->thumbnail_path
            : $screenshot->path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $screenshot->original_name, [
            'Cache-Control' => 'private, max-age=86400',
        ], 'inline');
    }
}
