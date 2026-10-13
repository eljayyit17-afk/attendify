<?php

namespace App\Livewire\Employee;

use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

abstract class PortalComponent extends Component
{
    public function boot(): void
    {
        Gate::authorize('create', AttendanceSession::class);
    }

    protected function employee(): User
    {
        $employee = User::query()->with('employeeProfile.schedule')->findOrFail(Auth::id());
        Gate::forUser($employee)->authorize('create', AttendanceSession::class);

        return $employee;
    }
}
