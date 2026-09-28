<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the Python analyzer service in /analyzer.
 */
class ScreenshotAnalyzer
{
    /**
     * Extract hashes, dimensions, on-screen text, AI categories and thumbnails for the given files.
     *
     * @param  list<array{id: int, path: string, thumbnail_path: string}>  $items
     * @param  array{read_clock: bool, read_title: bool, labels: list<array{id: int, prompt: string}>}  $options
     * @return list<array{id: int, md5?: string, phash?: string, width?: int, height?: int, file_size?: int, clock?: array{time: string, ambiguous: bool, text: string}|null, title?: string|null, categories?: list<array{id: int, score: float}>|null, error: string|null}>
     */
    public function extract(array $items, array $options): array
    {
        return $this->client()->post('/extract', ['items' => $items, ...$options])->throw()->json('results');
    }

    /**
     * Compare screenshots of one scan with each other and with earlier references.
     *
     * @param  list<array{id: int, path: string, md5: string, phash: string}>  $screenshots
     * @param  list<array{id: int, md5: string, phash: string}>  $references
     * @param  array<string, mixed>  $settings  the team's effective scanning settings
     * @return array{
     *     changes: list<array{id: int, previous_id: int, change_ratio: float, bbox: list<float>|null, regions: list<array{bbox: list<float>, ratio: float}>}>,
     *     duplicates: list<array{id: int, original_id: int}>,
     *     recycled: list<array{id: int, reference_id: int, distance: int, exact: bool}>,
     *     groups: list<list<int>>,
     * }
     */
    public function compare(array $screenshots, array $references, array $settings): array
    {
        return $this->client()->post('/compare', [
            'screenshots' => $screenshots,
            'references' => $references,
            'settings' => [
                'pixel_threshold' => $settings['pixel_threshold'],
                'similar_distance' => $settings['similar_distance'],
                'recycled_distance' => $settings['recycled_distance'],
                'ignore_regions' => $settings['ignore_regions'],
            ],
        ])->throw()->json();
    }

    /**
     * Report whether the analyzer is reachable and whether its AI classifier is installed.
     *
     * @return array{online: bool, classifier: bool}
     */
    public function health(): array
    {
        try {
            $response = Http::baseUrl(config('services.analyzer.url'))->connectTimeout(2)->timeout(3)->get('/health');

            return ['online' => $response->successful(), 'classifier' => (bool) $response->json('classifier')];
        } catch (ConnectionException) {
            return ['online' => false, 'classifier' => false];
        }
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
