<?php

namespace App\Livewire\Admin;

use App\Livewire\Forms\EmployeeForm;
use App\Models\Schedule;
use App\Models\User;
use App\Services\EmployeeManagement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeDirectory extends Component
{
    use WithPagination;

    public EmployeeForm $form;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $statusTargetId = null;

    #[Locked]
    public bool $targetActive = false;

    public bool $showEditor = false;

    public bool $showStatusConfirmation = false;

    public string $notice = '';

    public string $invitationNotice = '';

    public function boot(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function createEmployee(): void
    {
        Gate::authorize('create', User::class);
        $this->resetValidation();
        $this->form->reset();
        $this->editingId = null;
        $this->showEditor = true;
    }

    public function editEmployee(int $id): void
    {
        $employee = $this->employee($id);
        $this->resetValidation();
        $this->form->fillEmployee($employee);
        $this->editingId = $employee->id;
        $this->showEditor = true;
    }

    public function saveEmployee(EmployeeManagement $management): void
    {
        $employee = $this->editingId ? $this->employee($this->editingId) : null;
        $data = $this->form->validated($employee);
        $isNew = $employee === null;
        $emailChanged = $employee && $employee->email !== $data['email'];
        try {
            $employee = $management->save($employee, $data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['form.email' => 'The email or employee ID is already in use. Refresh and check both values.']);
        }
        $this->showEditor = false;
        $this->notice = $isNew ? 'Employee created.' : 'Employee updated.';
        $this->invitationNotice = '';
        $this->resetPage();
        if ($isNew || $emailChanged) {
            $this->requestInvitation($employee, $management);
        }
    }

    public function confirmStatus(int $id): void
    {
        $employee = $this->employee($id);
        $this->statusTargetId = $employee->id;
        $this->targetActive = ! $employee->is_active;
        $this->showStatusConfirmation = true;
    }

    public function changeStatus(EmployeeManagement $management): void
    {
        abort_unless($this->statusTargetId !== null, 422);
        $management->setActive($this->employee($this->statusTargetId), $this->targetActive);
        $this->notice = $this->targetActive ? 'Employee activated.' : 'Employee deactivated. Existing sessions and password links have been revoked.';
        $this->invitationNotice = '';
        $this->showStatusConfirmation = false;
        $this->statusTargetId = null;
    }

    public function resendInvitation(int $id, EmployeeManagement $management): void
    {
        $this->notice = '';
        $this->requestInvitation($this->employee($id), $management);
    }

    private function requestInvitation(User $employee, EmployeeManagement $management): void
    {
        try {
            $management->invite($employee);
            $this->invitationNotice = 'Password setup email queued. The employee will choose their own password.';
        } catch (ValidationException $exception) {
            $this->invitationNotice = $exception->errors()['invitation'][0];
        } catch (\Throwable) {
            // Never include transport errors, credentials, or reset tokens in responses.
            $this->invitationNotice = 'Account saved, but the invitation could not be queued. Check email delivery and retry using Send invitation.';
        }
    }

    private function employee(int $id): User
    {
        $employee = User::query()->with('employeeProfile')->findOrFail($id);
        Gate::authorize('update', $employee);

        return $employee;
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $search = mb_substr(trim($this->search), 0, 100);

        return [
            'employees' => User::query()->where('role', 'employee')
                ->select(['id', 'name', 'email', 'is_active', 'requires_password_setup', 'created_at'])
                ->with('employeeProfile.schedule')
                ->when(in_array($this->status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $this->status === 'active'))
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('employeeProfile', fn ($query) => $query->where('employee_id', 'like', '%'.$search.'%')
                        ->orWhere('department', 'like', '%'.$search.'%')->orWhere('position', 'like', '%'.$search.'%'))))
                ->orderBy('name')->orderBy('id')->paginate(15),
            'schedules' => $this->showEditor ? Schedule::query()->orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }
}
