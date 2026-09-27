<?php

namespace App\Enums;

enum FindingType: string
{
    case ExactDuplicate = 'exact_duplicate';
    case Recycled = 'recycled';
    case Idle = 'idle';
    case TimeGap = 'time_gap';

    /**
     * Get the display label for the finding type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ExactDuplicate => 'File identik',
            self::Recycled => 'Gambar daur ulang',
            self::Idle => 'Layar diam',
            self::TimeGap => 'Celah waktu',
        };
    }
}
