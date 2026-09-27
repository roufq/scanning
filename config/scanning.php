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

    'extract_chunk_size' => 25,

];
