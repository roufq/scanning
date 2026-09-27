<?php

namespace App\Http\Controllers\Scans;

use App\Actions\Scans\ParseScreenshotTimestamp;
use App\Enums\TimestampSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scans\StoreScreenshotsRequest;
use App\Models\Scan;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class ScanScreenshotController extends Controller
{
    /**
     * Store one chunk of uploaded screenshots.
     */
    public function store(StoreScreenshotsRequest $request, Team $current_team, Scan $scan, ParseScreenshotTimestamp $parseTimestamp): JsonResponse
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('files');
        $modifiedAt = (array) $request->validated('modified_at', []);

        foreach ($files as $index => $file) {
            $name = $file->getClientOriginalName();
            $fromName = $parseTimestamp->handle($name);
            $fromFile = isset($modifiedAt[$index]) ? CarbonImmutable::parse($modifiedAt[$index]) : null;

            $scan->screenshots()->create([
                'original_name' => mb_substr($name, 0, 255),
                'path' => $file->store($scan->storageDirectory().'/originals', 'local'),
                'taken_at' => $fromName ?? $fromFile,
                'taken_at_source' => match (true) {
                    $fromName !== null => TimestampSource::Filename,
                    $fromFile !== null => TimestampSource::FileModified,
                    default => TimestampSource::Unknown,
                },
                'file_size' => $file->getSize(),
            ]);
        }

        return response()->json([
            'uploaded' => count($files),
            'total' => $scan->screenshots()->count(),
        ]);
    }
}
