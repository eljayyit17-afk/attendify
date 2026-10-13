<?php

namespace App\Models;

use App\Casts\UtcInstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array', 'created_at' => UtcInstant::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Attendance audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Attendance audit records are append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<AttendanceSession, $this> */
    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }
}
