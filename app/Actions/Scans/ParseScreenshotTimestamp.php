<?php

namespace App\Actions\Scans;

use Carbon\CarbonImmutable;

class ParseScreenshotTimestamp
{
    /**
     * Patterns tried in order. Named groups: y, m, d, h, i and optional s.
     *
     * @var list<string>
     */
    protected const PATTERNS = [
        // 2026-09-27_08-15-00, 20260927081500, Screenshot 2026-09-27 at 08.15.00
        '/(?<!\d)(?<y>20\d{2})[-_.]?(?<m>0[1-9]|1[0-2])[-_.]?(?<d>0[1-9]|[12]\d|3[01])\D{0,5}?(?<h>[01]\d|2[0-3])[-_.:h]?(?<i>[0-5]\d)(?:[-_.:m]?(?<s>[0-5]\d))?(?!\d)/',
        // 27-09-2026 08.15.00
        '/(?<!\d)(?<d>0[1-9]|[12]\d|3[01])[-_.](?<m>0[1-9]|1[0-2])[-_.](?<y>20\d{2})\D{1,5}?(?<h>[01]\d|2[0-3])[-_.:](?<i>[0-5]\d)(?:[-_.:](?<s>[0-5]\d))?(?!\d)/',
    ];

    /**
     * Read the capture time from a screenshot file name, if it contains one.
     */
    public function handle(string $filename): ?CarbonImmutable
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $name, $match) && checkdate((int) $match['m'], (int) $match['d'], (int) $match['y'])) {
                return CarbonImmutable::create(
                    (int) $match['y'], (int) $match['m'], (int) $match['d'],
                    (int) $match['h'], (int) $match['i'], (int) ($match['s'] ?? 0),
                );
            }
        }

        if (preg_match('/(?<!\d)(1\d{9})(\d{3})?(?!\d)/', $name, $match)) {
            return CarbonImmutable::createFromTimestamp((int) $match[1], config('app.timezone'));
        }

        return null;
    }
}
