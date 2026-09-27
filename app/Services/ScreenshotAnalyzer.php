<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the Python analyzer service in /analyzer.
 */
class ScreenshotAnalyzer
{
    /**
     * Extract hashes, dimensions and thumbnails for the given files.
     *
     * @param  list<array{id: int, path: string, thumbnail_path: string}>  $items
     * @return list<array{id: int, md5?: string, phash?: string, width?: int, height?: int, file_size?: int, clock?: array{time: string, ambiguous: bool, text: string}|null, error: string|null}>
     */
    public function extract(array $items): array
    {
        return $this->client()->post('/extract', [
            'items' => $items,
            'read_clock' => config('scanning.read_screen_clock'),
        ])->throw()->json('results');
    }

    /**
     * Compare screenshots of one scan with each other and with earlier references.
     *
     * @param  list<array{id: int, path: string, md5: string, phash: string}>  $screenshots
     * @param  list<array{id: int, md5: string, phash: string}>  $references
     * @return array{
     *     changes: list<array{id: int, previous_id: int, change_ratio: float, bbox: list<float>|null, regions: list<array{bbox: list<float>, ratio: float}>}>,
     *     duplicates: list<array{id: int, original_id: int}>,
     *     recycled: list<array{id: int, reference_id: int, distance: int, exact: bool}>,
     *     groups: list<list<int>>,
     * }
     */
    public function compare(array $screenshots, array $references): array
    {
        return $this->client()->post('/compare', [
            'screenshots' => $screenshots,
            'references' => $references,
            'settings' => [
                'pixel_threshold' => config('scanning.pixel_threshold'),
                'similar_distance' => config('scanning.similar_distance'),
                'recycled_distance' => config('scanning.recycled_distance'),
                'ignore_regions' => config('scanning.ignore_regions'),
            ],
        ])->throw()->json();
    }

    /**
     * Build the base HTTP request for the analyzer.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('services.analyzer.url'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(config('services.analyzer.timeout'));
    }
}
