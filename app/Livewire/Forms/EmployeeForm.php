<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

class EmployeeForm extends Form
{
    public string $name = '';

    public string $email = '';

    public string $employeeCode = '';

    public string $department = '';

    public string $position = '';

    public string $hireDate = '';

    public string $scheduleId = '';

    public function fillEmployee(User $employee): void
    {
        $this->name = $employee->name;
        $this->email = $employee->email;
        $this->employeeCode = $employee->employeeProfile->employee_id ?? '';
        $this->department = $employee->employeeProfile->department ?? '';
        $this->position = $employee->employeeProfile->position ?? '';
        $this->hireDate = $employee->employeeProfile?->hire_date?->format('Y-m-d') ?? '';
        $this->scheduleId = (string) ($employee->employeeProfile->schedule_id ?? '');
    }

    /** @return array<string, mixed> */
    public function validated(?User $employee): array
    {
        $this->name = trim($this->name);
        $this->email = mb_strtolower(trim($this->email));
        $this->employeeCode = strtoupper(trim($this->employeeCode));
        $this->department = trim($this->department);
        $this->position = trim($this->position);

        return $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->id)],
            'employeeCode' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('employee_profiles', 'employee_id')->ignore($employee?->id, 'user_id')],
            'department' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'hireDate' => ['required', 'date_format:Y-m-d'],
            'scheduleId' => ['nullable', 'integer', Rule::exists('schedules', 'id')],
        ], [], ['employeeCode' => 'employee ID', 'hireDate' => 'hire date', 'scheduleId' => 'schedule']);
    }
}
