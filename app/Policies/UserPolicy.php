<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === 'admin' && $actor->is_active;
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $employee): bool
    {
        return $this->viewAny($actor) && $employee->role === 'employee';
    }
}
