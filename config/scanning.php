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

    /*
    |--------------------------------------------------------------------------
    | Application Categories (AI + keywords)
    |--------------------------------------------------------------------------
    |
    | Screenshots are sorted into categories by matching keywords in the
    | title/tab bar (OCR) first, then by the CLIP model when no keyword
    | matches. A non-productive category with an AI confidence of at least
    | ai_min_confidence becomes a "non-work application" finding.
    |
    | These are the defaults a team starts with; each team can edit its own
    | categories in the scan settings page.
    |
    */

    'classify_screenshots' => (bool) env('SCANNING_CLASSIFY', true),

    'ai_min_confidence' => (float) env('SCANNING_AI_MIN_CONFIDENCE', 0.5),

    'default_categories' => [
        [
            'name' => 'Spreadsheet',
            'prompt' => 'a screenshot of a spreadsheet application like Excel or Google Sheets',
            'keywords' => ['excel', 'google sheets', 'spreadsheet', '.xlsx', '.xls', '.csv'],
            'is_productive' => true,
        ],
        [
            'name' => 'Dokumen',
            'prompt' => 'a screenshot of a text document editor like Word or Google Docs',
            'keywords' => ['word', 'google docs', '.docx', '.doc', '.pdf', 'acrobat'],
            'is_productive' => true,
        ],
        [
            'name' => 'Kode / IDE',
            'prompt' => 'a screenshot of a code editor or programming IDE',
            'keywords' => ['visual studio', 'vscode', 'phpstorm', 'intellij', 'github', 'gitlab', 'terminal', 'powershell'],
            'is_productive' => true,
        ],
        [
            'name' => 'Email & kalender',
            'prompt' => 'a screenshot of an email inbox or a calendar',
            'keywords' => ['outlook', 'gmail', 'inbox', 'thunderbird'],
            'is_productive' => true,
        ],
        [
            'name' => 'Rapat online',
            'prompt' => 'a screenshot of a video conference meeting with participants',
            'keywords' => ['zoom', 'google meet', 'microsoft teams'],
            'is_productive' => true,
        ],
        [
            'name' => 'Aplikasi bisnis',
            'prompt' => 'a screenshot of a business web application dashboard with tables and charts',
            'keywords' => ['dashboard', 'erp', 'crm', 'admin'],
            'is_productive' => true,
        ],
        [
            'name' => 'Video / streaming',
            'prompt' => 'a screenshot of a YouTube or Netflix video player',
            'keywords' => ['youtube', 'netflix', 'vidio', 'twitch', 'disney+', 'prime video'],
            'is_productive' => false,
        ],
        [
            'name' => 'Game',
            'prompt' => 'a screenshot of a video game',
            'keywords' => ['steam', 'roblox', 'minecraft', 'epic games', 'mobile legends'],
            'is_productive' => false,
        ],
        [
            'name' => 'Media sosial',
            'prompt' => 'a screenshot of a social media feed like Facebook, Instagram, TikTok or Twitter',
            'keywords' => ['facebook', 'instagram', 'tiktok', 'twitter', 'x.com', 'reddit'],
            'is_productive' => false,
        ],
        [
            'name' => 'Belanja online',
            'prompt' => 'a screenshot of an online shopping website with products and prices',
            'keywords' => ['shopee', 'tokopedia', 'lazada', 'bukalapak', 'blibli', 'amazon'],
            'is_productive' => false,
        ],
    ],

    'extract_chunk_size' => 25,

];
