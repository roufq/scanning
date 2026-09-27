import { image as screenshotImage } from '@/routes/scans/screenshots';
import type { ScanStatus, ScreenshotActivity } from '@/types/scans';

export const activityColors: Record<ScreenshotActivity, string> = {
    active: 'bg-emerald-500',
    low: 'bg-amber-400',
    idle: 'bg-red-500',
    duplicate: 'bg-fuchsia-700',
    unknown: 'bg-zinc-400',
};

export const activityBorders: Record<ScreenshotActivity, string> = {
    active: 'border-emerald-500',
    low: 'border-amber-400',
    idle: 'border-red-500',
    duplicate: 'border-fuchsia-700',
    unknown: 'border-zinc-300 dark:border-zinc-600',
};

export const statusVariants: Record<
    ScanStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    uploading: 'outline',
    queued: 'secondary',
    processing: 'secondary',
    completed: 'default',
    failed: 'destructive',
};

export function screenshotUrl(
    teamSlug: string,
    scanId: number,
    screenshotId: number,
    variant: 'image' | 'thumbnail' = 'thumbnail',
): string {
    return screenshotImage.url({
        current_team: teamSlug,
        scan: scanId,
        screenshot: screenshotId,
        variant,
    });
}

export function formatPercent(ratio: number | null): string {
    if (ratio === null) {
        return '-';
    }

    return `${(ratio * 100).toFixed(ratio < 0.01 ? 2 : 1)}%`;
}

export function formatTime(dateTime: string | null): string {
    return dateTime ? dateTime.slice(11, 16) : '--:--';
}

export function formatDate(date: string | null): string {
    if (!date) {
        return '-';
    }

    return new Date(`${date.slice(0, 10)}T00:00:00`).toLocaleDateString(
        'id-ID',
        { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' },
    );
}

/**
 * Format a timestamp in the browser's local time as Y-m-d\TH:i:s.
 */
export function toLocalIso(timestamp: number): string {
    const date = new Date(timestamp);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
