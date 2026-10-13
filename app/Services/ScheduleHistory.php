<?php

namespace App\Services;

use App\Models\ScheduleAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScheduleHistory
{
    public function sync(int $userId): void
    {
        if (! Schema::hasTable('schedule_assignments')) {
            return;
        }
        DB::transaction(function () use ($userId) {
            $user = User::query()->lockForUpdate()->find($userId);
            if (! $user) {
                return;
            }
            $schedule = $user->employeeProfile?->schedule;
            $enabled = $user->role === 'employee' && $user->is_active && ! $user->requires_password_setup && $schedule?->isConfigured();
            $current = ScheduleAssignment::where('user_id', $userId)->whereNull('effective_until')->lockForUpdate()->first();
            $values = $enabled ? $schedule->only(['name', 'timezone', 'start_time', 'end_time', 'working_days']) : null;
            if ($current && $values !== null && $values === $current->only(array_keys($values))) {
                return;
            }
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $current?->update(['effective_until' => $now]);
            if ($values) {
                ScheduleAssignment::create([...$values, 'user_id' => $userId, 'effective_from' => $now]);
            }
        });
    }
}
