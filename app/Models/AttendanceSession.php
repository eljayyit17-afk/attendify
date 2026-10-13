<?php

namespace App\Models;

use App\Casts\UtcInstant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $work_date
 * @property string $schedule_name
 * @property string $schedule_timezone
 * @property CarbonImmutable $scheduled_start_at
 * @property CarbonImmutable $scheduled_end_at
 * @property CarbonImmutable $clock_in_at
 * @property CarbonImmutable|null $clock_out_at
 * @property int $version
 * @property-read User $user
 */
class AttendanceSession extends Model
{
    protected $guarded = ['id', 'active_user_id'];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => UtcInstant::class, 'scheduled_end_at' => UtcInstant::class,
            'clock_in_at' => UtcInstant::class, 'clock_out_at' => UtcInstant::class,
            'created_at' => UtcInstant::class, 'updated_at' => UtcInstant::class,
            'version' => 'integer',
        ];
    }

    public function newTimestamp(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AttendanceCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function durationSeconds(?CarbonImmutable $asOf = null): int
    {
        $end = $this->clock_out_at ?? $asOf ?? CarbonImmutable::now('UTC');

        return max(0, $end->getTimestamp() - $this->clock_in_at->getTimestamp());
    }

    public function durationLabel(): string
    {
        if ($this->clock_out_at === null) {
            return 'Not finalized';
        }
        $seconds = $this->durationSeconds();

        return sprintf('%dh %02dm %02ds', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function isOverdue(): bool
    {
        return $this->clock_out_at === null && $this->durationSeconds() > 86400;
    }

    public function statusLabel(): string
    {
        return $this->clock_out_at ? 'Completed' : ($this->isOverdue() ? 'Needs correction' : 'Clocked in');
    }
}
