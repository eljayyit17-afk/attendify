<?php

namespace App\Livewire\Employee;

use App\Services\AttendanceService;
use App\Services\ShiftRules;
use App\Services\WorkdayReport;
use Carbon\CarbonImmutable;

class PortalOverview extends PortalComponent
{
    public string $notice = '';

    public function clockIn(AttendanceService $service): void
    {
        $this->resetValidation();
        $this->notice = '';
        $service->clockIn();
        $this->notice = 'Clock-in recorded.';
    }

    public function clockOut(AttendanceService $service): void
    {
        $this->resetValidation();
        $this->notice = '';
        $service->clockOut();
        $this->notice = 'Clock-out recorded. Your working time is finalized.';
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $employee = $this->employee();
        $schedule = $employee->employeeProfile?->schedule;
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $rules = app(ShiftRules::class);
        $workDate = $rules->currentWorkDate($schedule, $now);
        $active = $employee->attendanceSessions()->whereNull('clock_out_at')->first();
        $record = $active ?? $employee->attendanceSessions()->where('work_date', $workDate)->first();
        $displayDate = $record->work_date ?? $workDate;
        $classification = app(WorkdayReport::class)->rows($displayDate, $displayDate)->first();
        $dayStatus = $classification['status'] ?? 'Unscheduled';

        return [
            'employee' => $employee, 'schedule' => $schedule, 'record' => $record,
            'workDate' => $displayDate, 'now' => $now, 'dayStatus' => $dayStatus,
            'canClockIn' => ! $record && ! in_array($dayStatus, ['VL', 'SL', 'Holiday'], true) && $schedule && $rules->clockInWindow($schedule, $now) !== null,
            'recentRecords' => $employee->attendanceSessions()->latest('work_date')->limit(5)->get(),
        ];
    }
}
