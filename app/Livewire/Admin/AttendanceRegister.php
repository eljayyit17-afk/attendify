<?php

namespace App\Livewire\Admin;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceRegister extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public int $editingVersion = 0;

    public bool $showCorrection = false;

    public string $clockInLocal = '';

    public string $clockOutLocal = '';

    public string $reason = '';

    public string $notice = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', User::class);
        abort_if(auth()->user()?->requires_password_setup, 403);
    }

    public function editRecord(int $id): void
    {
        $record = AttendanceSession::query()->findOrFail($id);
        Gate::authorize('correct', $record);
        $this->editingId = $id;
        $this->editingVersion = $record->version;
        $this->clockInLocal = $record->clock_in_at->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:s');
        $this->clockOutLocal = $record->clock_out_at?->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:s') ?? '';
        $this->reason = '';
        $this->resetValidation();
        $this->showCorrection = true;
    }

    public function saveCorrection(AttendanceService $service): void
    {
        abort_unless($this->editingId !== null, 422);
        $service->correct($this->editingId, $this->clockInLocal, $this->clockOutLocal, $this->reason, $this->editingVersion);
        $this->showCorrection = false;
        $this->notice = 'Attendance corrected. The reason and original values were saved to the audit trail.';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'records' => AttendanceSession::query()->with('user:id,name')->latest('work_date')->latest('id')->paginate(15),
            'editingRecord' => $this->showCorrection && $this->editingId
                ? AttendanceSession::query()->with(['user:id,name', 'corrections' => fn ($query) => $query->with('actor:id,name')->latest('id')->limit(10)])->findOrFail($this->editingId) : null,
        ];
    }
}
