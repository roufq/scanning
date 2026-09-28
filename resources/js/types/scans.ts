export type ScanStatus =
    | 'uploading'
    | 'queued'
    | 'processing'
    | 'completed'
    | 'failed';

export type ScreenshotActivity =
    | 'active'
    | 'low'
    | 'idle'
    | 'cursor_only'
    | 'duplicate'
    | 'unknown';

export type FindingType =
    | 'exact_duplicate'
    | 'recycled'
    | 'idle'
    | 'time_gap'
    | 'clock_mismatch'
    | 'mouse_jiggler'
    | 'non_work';

export type Box = [number, number, number, number];

export type ScanListItem = {
    id: number;
    employee: string;
    workDate: string | null;
    status: ScanStatus;
    statusLabel: string;
    screenshotsCount: number;
    findingsCount: number;
    createdAt: string | null;
};

export type ScanDetail = {
    id: number;
    employee: string;
    workDate: string | null;
    status: ScanStatus;
    statusLabel: string;
    isRunning: boolean;
    processedCount: number;
    screenshotsCount: number;
    error: string | null;
    analyzedAt: string | null;
};

export type ScreenshotItem = {
    id: number;
    name: string;
    takenAt: string | null;
    takenAtSource: 'filename' | 'file_modified' | 'unknown';
    activity: ScreenshotActivity;
    changeRatio: number | null;
    changeBbox: Box | null;
    changeRegions: Box[];
    screenClock: string | null;
    windowTitle: string | null;
    category: {
        name: string;
        isProductive: boolean;
        source: 'keyword' | 'ai' | null;
        confidence: number | null;
        keyword: string | null;
    } | null;
    similarityGroup: number | null;
    hasThumbnail: boolean;
    error: string | null;
};

export type FindingItem = {
    id: number;
    type: FindingType;
    screenshotId: number;
    score: number | null;
    details: Record<string, unknown> | null;
    description: string;
    related: {
        id: number;
        scanId: number;
        name: string;
        takenAt: string | null;
        scanDate: string | null;
    } | null;
};

export type ScanLabels = {
    activity: Record<ScreenshotActivity, string>;
    finding: Record<FindingType, string>;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type ScanCategoryItem = {
    id: number;
    name: string;
    prompt: string;
    keywords: string[];
    isProductive: boolean;
    isEnabled: boolean;
};

export type ScanSettingsValues = {
    idle_change_ratio: number;
    low_change_ratio: number;
    time_gap_minutes: number;
    clock_mismatch_minutes: number;
    jiggler_min_streak: number;
    similar_distance: number;
    read_screen_clock: boolean;
    classify_screenshots: boolean;
    ai_min_confidence: number;
};
