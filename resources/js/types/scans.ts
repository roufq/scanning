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
    | 'duplicate'
    | 'unknown';

export type FindingType = 'exact_duplicate' | 'recycled' | 'idle' | 'time_gap';

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
    changeBbox: [number, number, number, number] | null;
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
