<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'employee' && $user->is_active && ! $user->requires_password_setup;
    }

    public function cancel(User $user, LeaveRequest $leave): bool
    {
        return $this->create($user) && $leave->user_id === $user->id;
    }

    public function decide(User $user, LeaveRequest $leave): bool
    {
        return $user->role === 'admin' && $user->is_active && ! $user->requires_password_setup;
    }
}
