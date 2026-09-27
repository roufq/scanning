<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    */

    'max_screenshots_per_scan' => (int) env('SCANNING_MAX_SCREENSHOTS', 1000),

    'max_file_size_kb' => (int) env('SCANNING_MAX_FILE_SIZE_KB', 20480),

    'files_per_upload_request' => 20,

    /*
    |--------------------------------------------------------------------------
    | Analysis Thresholds
    |--------------------------------------------------------------------------
    |
    | change_ratio is the fraction of pixels that changed compared with the
    | previous screenshot (0.003 = 0.3% of the screen).
    |
    */

    'idle_change_ratio' => (float) env('SCANNING_IDLE_CHANGE_RATIO', 0.003),

    'low_change_ratio' => (float) env('SCANNING_LOW_CHANGE_RATIO', 0.015),

    'pixel_threshold' => (int) env('SCANNING_PIXEL_THRESHOLD', 25),

    'similar_distance' => (int) env('SCANNING_SIMILAR_DISTANCE', 4),

    'recycled_distance' => (int) env('SCANNING_RECYCLED_DISTANCE', 0),

    'time_gap_minutes' => (int) env('SCANNING_TIME_GAP_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Screen Clock (OCR)
    |--------------------------------------------------------------------------
    |
    | The analyzer reads the clock in the taskbar with Tesseract and compares
    | it with the capture time taken from the file name / file date.
    |
    */

    'read_screen_clock' => (bool) env('SCANNING_READ_SCREEN_CLOCK', true),

    'clock_mismatch_minutes' => (int) env('SCANNING_CLOCK_MISMATCH_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Ignored Screen Regions
    |--------------------------------------------------------------------------
    |
    | Normalized [x0, y0, x1, y1] areas whose changes do not count as activity,
    | e.g. the taskbar clock and tray icons in the bottom-right corner.
    |
    */

    'ignore_regions' => [
        [0.70, 0.93, 1.0, 1.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mouse Jiggler Detection
    |--------------------------------------------------------------------------
    |
    | A screenshot counts as "cursor only" when everything that changed fits in
    | a few small areas (the old and new cursor position). Several of those in a
    | row suggest the mouse is being moved without real work.
    |
    */

    'cursor_max_regions' => 2,

    'cursor_max_region_size' => 0.04,

    'jiggler_min_streak' => (int) env('SCANNING_JIGGLER_MIN_STREAK', 2),

    'extract_chunk_size' => 25,

];
