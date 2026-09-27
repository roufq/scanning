<?php

namespace App\Models;

use App\Enums\ScanStatus;
use Database\Factories\ScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $employee_id
 * @property int|null $user_id
 * @property ScanStatus $status
 * @property Carbon|null $work_date
 * @property int $processed_count
 * @property string|null $batch_id
 * @property string|null $error
 * @property Carbon|null $analyzed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Employee $employee
 * @property-read User|null $user
 * @property-read Collection<int, Screenshot> $screenshots
 * @property-read Collection<int, ScanFinding> $findings
 */
#[Fillable(['team_id', 'employee_id', 'user_id', 'status', 'work_date', 'processed_count', 'batch_id', 'error', 'analyzed_at'])]
class Scan extends Model
{
    /** @use HasFactory<ScanFactory> */
    use HasFactory;

    /**
     * The storage directory that holds the scan's files.
     */
    public function storageDirectory(): string
    {
        return "screenshots/{$this->id}";
    }

    /**
     * Get the team the scan belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the employee whose screenshots were scanned.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who uploaded the scan.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the uploaded screenshots.
     *
     * @return HasMany<Screenshot, $this>
     */
    public function screenshots(): HasMany
    {
        return $this->hasMany(Screenshot::class);
    }

    /**
     * Get the analysis findings.
     *
     * @return HasMany<ScanFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(ScanFinding::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScanStatus::class,
            'work_date' => 'date',
            'analyzed_at' => 'datetime',
        ];
    }
}
