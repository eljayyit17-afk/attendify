<?php

namespace App\Models;

use App\Services\ScheduleHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string $timezone
 * @property list<int>|null $working_days
 */
class Schedule extends Model
{
    protected static function booted(): void
    {
        static::saved(function (self $schedule) {
            foreach ($schedule->employeeProfiles()->pluck('user_id') as $userId) {
                app(ScheduleHistory::class)->sync($userId);
            }
        });
    }

    protected $fillable = ['name', 'start_time', 'end_time', 'timezone', 'working_days'];

    protected function casts(): array
    {
        return ['working_days' => 'array'];
    }

    public function isConfigured(): bool
    {
        if (! $this->start_time || ! $this->end_time || empty($this->working_days)
            || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $this->start_time)
            || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $this->end_time)) {
            return false;
        }
        $start = (int) substr($this->start_time, 0, 2) * 60 + (int) substr($this->start_time, 3, 2);
        $end = (int) substr($this->end_time, 0, 2) * 60 + (int) substr($this->end_time, 3, 2);
        $length = ($end - $start + 1440) % 1440;

        return $length > 0 && $length <= 960 && array_diff($this->working_days, range(1, 7)) === []
            && in_array($this->timezone, timezone_identifiers_list(), true);
    }

    public function daysLabel(): string
    {
        $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return implode(', ', array_map(fn ($day) => $days[$day] ?? '', $this->working_days ?? []));
    }

    /** @return HasMany<EmployeeProfile, $this> */
    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }
}
