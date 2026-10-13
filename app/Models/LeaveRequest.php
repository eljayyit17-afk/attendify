<?php

namespace App\Models;

use App\Casts\UtcInstant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $leave_type
 * @property string $start_date
 * @property string $end_date
 * @property string $status
 * @property string $reason
 * @property string|null $remarks
 * @property CarbonImmutable|null $decided_at
 * @property-read User $user
 * @property-read User|null $approver
 */
class LeaveRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => UtcInstant::class, 'created_at' => UtcInstant::class, 'updated_at' => UtcInstant::class];
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

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
