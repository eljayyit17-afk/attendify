<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;

class AttendanceSessionPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'employee' && $user->is_active && ! $user->requires_password_setup;
    }

    public function view(User $user, AttendanceSession $attendance): bool
    {
        return $this->create($user) && $attendance->user_id === $user->id;
    }

    public function correct(User $user, AttendanceSession $attendance): bool
    {
        return $user->role === 'admin' && $user->is_active && ! $user->requires_password_setup;
    }
}
