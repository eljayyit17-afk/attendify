<?php

namespace App\Models;

use App\Casts\UtcInstant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property string $name
 * @property string $timezone
 * @property string $start_time
 * @property string $end_time
 * @property list<int> $working_days
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_until
 */
class ScheduleAssignment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'effective_from' => UtcInstant::class, 'effective_until' => UtcInstant::class];
    }
}
