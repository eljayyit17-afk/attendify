<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin' && $user->is_active && ! $user->requires_password_setup;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->viewAny($user);
    }
}
