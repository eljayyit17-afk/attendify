<?php

namespace App\Models;

use App\Services\ScheduleHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $employee_id
 * @property string|null $department
 * @property string|null $position
 * @property Carbon|null $hire_date
 * @property int|null $schedule_id
 * @property-read Schedule|null $schedule
 */
class EmployeeProfile extends Model
{
    protected static function booted(): void
    {
        static::saved(function (self $profile) {
            if ($profile->wasRecentlyCreated || $profile->wasChanged('schedule_id')) {
                app(ScheduleHistory::class)->sync($profile->user_id);
            }
        });
        static::deleted(fn (self $profile) => app(ScheduleHistory::class)->sync($profile->user_id));
    }

    protected $fillable = ['employee_id', 'department', 'position', 'hire_date', 'schedule_id'];

    protected function casts(): array
    {
        return ['hire_date' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
