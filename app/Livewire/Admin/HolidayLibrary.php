<?php

namespace App\Livewire\Admin;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class HolidayLibrary extends Component
{
    use WithPagination;

    public string $name = '';

    public string $date = '';

    public string $timezone = 'Asia/Manila';

    public string $notice = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', User::class);
        abort_if(auth()->user()?->requires_password_setup, 403);
    }

    public function addHoliday(): void
    {
        $this->validate(['name' => 'required|string|max:100', 'timezone' => 'required|timezone:all',
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'holiday_date')->where('timezone', $this->timezone)]]);
        Holiday::create(['name' => trim($this->name), 'holiday_date' => $this->date, 'timezone' => $this->timezone]);
        $this->notice = 'Holiday added. Matching schedule workdays are excluded from automatic absence.';
        $this->reset(['name', 'date']);
    }

    public function removeHoliday(int $id): void
    {
        Holiday::findOrFail($id)->delete();
        $this->notice = 'Holiday removed. Historical reports will be recalculated.';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return ['holidays' => Holiday::orderByDesc('holiday_date')->paginate(15)];
    }
}
