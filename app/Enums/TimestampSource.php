<?php

namespace App\Enums;

enum TimestampSource: string
{
    case Filename = 'filename';
    case FileModified = 'file_modified';
    case Unknown = 'unknown';

    /**
     * Get the display label for the timestamp source.
     */
    public function label(): string
    {
        return match ($this) {
            self::Filename => 'Nama file',
            self::FileModified => 'Waktu modifikasi file',
            self::Unknown => 'Tidak diketahui',
        };
    }
}
