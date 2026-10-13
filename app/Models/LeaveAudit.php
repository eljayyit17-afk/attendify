<?php

namespace App\Models;

use App\Casts\UtcInstant;
use Illuminate\Database\Eloquent\Model;

class LeaveAudit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => UtcInstant::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Leave audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Leave audit records are append-only.'));
    }
}
