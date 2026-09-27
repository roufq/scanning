<?php

use App\Actions\Scans\ParseScreenshotTimestamp;

test('capture time is read from common screenshot file names', function (string $filename, ?string $expected) {
    $parsed = (new ParseScreenshotTimestamp)->handle($filename);

    expect($parsed?->format('Y-m-d H:i:s'))->toBe($expected);
})->with([
    ['SS_20260927_081500.png', '2026-09-27 08:15:00'],
    ['20260927081500.jpg', '2026-09-27 08:15:00'],
    ['Screenshot 2026-09-27 at 08.15.30.png', '2026-09-27 08:15:30'],
    ['capture_2026-09-27_14-05.jpeg', '2026-09-27 14:05:00'],
    ['27-09-2026 08.15.00.png', '2026-09-27 08:15:00'],
    ['screen_1790496900.png', '2026-09-27 08:15:00'],
    ['SS_20260231_081500.png', null],
    ['laporan-kerja.png', null],
]);
