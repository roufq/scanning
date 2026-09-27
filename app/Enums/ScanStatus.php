<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Uploading = 'uploading';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Uploading => 'Mengunggah',
            self::Queued => 'Menunggu antrean',
            self::Processing => 'Dianalisis',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    /**
     * Determine whether the analysis is still running.
     */
    public function isRunning(): bool
    {
        return in_array($this, [self::Queued, self::Processing], true);
    }
}
