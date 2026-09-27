<?php

namespace App\Enums;

enum ScreenshotActivity: string
{
    case Active = 'active';
    case Low = 'low';
    case Idle = 'idle';
    case CursorOnly = 'cursor_only';
    case Duplicate = 'duplicate';
    case Unknown = 'unknown';

    /**
     * Get the display label for the activity level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Low => 'Aktivitas rendah',
            self::Idle => 'Layar diam',
            self::CursorOnly => 'Hanya kursor bergerak',
            self::Duplicate => 'Duplikat / daur ulang',
            self::Unknown => 'Tidak diketahui',
        };
    }
}
