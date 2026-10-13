<?php

namespace App\Livewire\Admin;

use App\Models\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class WorkSchedules extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    public bool $showEditor = false;

    public string $name = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $timezone = 'Asia/Manila';

    /** @var list<int|string> */
    public array $workingDays = [];

    public string $notice = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', Schedule::class);
    }

    public function createSchedule(): void
    {
        Gate::authorize('create', Schedule::class);
        $this->reset(['editingId', 'name', 'startTime', 'endTime', 'workingDays', 'timezone']);
        $this->resetValidation();
        $this->showEditor = true;
    }

    public function editSchedule(int $id): void
    {
        $schedule = Schedule::query()->findOrFail($id);
        Gate::authorize('update', $schedule);
        $this->editingId = $id;
        $this->name = $schedule->name;
        $this->startTime = substr($schedule->start_time ?? '', 0, 5);
        $this->endTime = substr($schedule->end_time ?? '', 0, 5);
        $this->timezone = $schedule->timezone;
        $this->workingDays = $schedule->working_days ?? [];
        $this->resetValidation();
        $this->showEditor = true;
    }

    public function saveSchedule(): void
    {
        $schedule = $this->editingId ? Schedule::query()->findOrFail($this->editingId) : new Schedule;
        Gate::authorize($schedule->exists ? 'update' : 'create', $schedule->exists ? $schedule : Schedule::class);
        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('schedules', 'name')->ignore($this->editingId)],
            'startTime' => ['required', 'date_format:H:i'], 'endTime' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone:all'],
            'workingDays' => ['required', 'array', 'min:1', 'max:7'],
            'workingDays.*' => ['integer', 'between:1,7', 'distinct'],
        ]);
        $schedule->fill([
            'name' => $this->name, 'start_time' => $this->startTime.':00', 'end_time' => $this->endTime.':00',
            'timezone' => $this->timezone, 'working_days' => array_values(array_unique(array_map(intval(...), $this->workingDays))),
        ]);
        if (! $schedule->isConfigured()) {
            throw ValidationException::withMessages(['endTime' => 'A shift must be longer than zero and no longer than 16 hours. An earlier end time means the shift ends the next day.']);
        }
        $schedule->save();
        $this->showEditor = false;
        $this->notice = 'Schedule saved. Existing attendance records keep their original shift details.';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return ['schedules' => Schedule::query()->withCount('employeeProfiles')->orderBy('name')->paginate(15)];
    }
}
