<?php

namespace App\Livewire\Admin;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\AttendanceAnalytics;
use App\Services\AttendancePdf;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkdaySummary extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $employeeId = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $grouping = 'daily';

    public function boot(): void
    {
        app(AttendanceAnalytics::class)->authorize();
    }

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->toDateString();
        if ($this->from === '') {
            $this->from = $today;
        }
        if ($this->to === '') {
            $this->to = $today;
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'department') {
            $this->employeeId = '';
        }
        if (in_array($property, ['from', 'to', 'status', 'employeeId', 'department', 'grouping'], true)) {
            $this->resetPage();
        }
    }

    public function applyPeriod(string $period): void
    {
        $this->validate(['grouping' => 'required|in:daily,weekly,monthly']);
        abort_unless(in_array($period, ['daily', 'weekly', 'monthly'], true), 422);
        $today = CarbonImmutable::now('Asia/Manila');
        $this->from = match ($period) {
            'weekly' => $today->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), 'monthly' => $today->startOfMonth()->toDateString(), default => $today->toDateString()
        };
        $this->to = $today->toDateString();
        $this->grouping = $period;
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'status' => $this->status, 'employeeId' => $this->employeeId, 'department' => $this->department, 'grouping' => $this->grouping];
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = app(AttendanceAnalytics::class)->rows($this->filters());

        return response()->streamDownload(function () use ($rows) {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('Unable to stream report.');
            }
            fputcsv($file, ['Employee', 'Employee ID', 'Current department', 'Work date', 'Status', 'Schedule timezone', 'Clock in (Manila)', 'Clock out (Manila)', 'Finalized working seconds'], escape: '');
            foreach ($rows as $row) {
                $safe = fn ($value) => is_string($value) && preg_match('/^\s*[=+\-@]|^[\t\r\n]/', $value) ? "'".$value : $value;
                fputcsv($file, [$safe($row['employee']), $safe($row['employee_code']), $safe($row['department']), $row['date'], $row['status'], $row['timezone'],
                    $row['session']?->clock_in_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $row['session']?->clock_out_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $row['session']?->clock_out_at ? $row['session']->durationSeconds() : null], escape: '');
            }
            fclose($file);
        }, 'attendify-workdays-'.$this->from.'-'.$this->to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function exportPdf(): StreamedResponse
    {
        $contents = app(AttendancePdf::class)->generate($this->filters());

        return response()->streamDownload(fn () => print ($contents), 'attendify-attendance-'.$this->from.'-'.$this->to.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $analytics = app(AttendanceAnalytics::class);
        try {
            $summary = $analytics->summarize($this->filters(), $this->getPage());
            foreach (['from', 'to', 'employeeId', 'department', 'status', 'grouping'] as $field) {
                $this->resetValidation($field);
            }
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            $summary = ['counts' => [], 'periods' => [], 'departmentCounts' => [], 'details' => collect(), 'total' => 0, 'finalizedSeconds' => 0, 'incomplete' => 0, 'employeeCount' => 0, 'attendanceRate' => null, 'onTimeRate' => null];
        }

        return ['counts' => $summary['counts'], 'summary' => $summary,
            'rows' => new LengthAwarePaginator($summary['details'], $summary['total'], 50, $this->getPage(), ['path' => route('admin.reports')]),
            'employees' => User::where('role', 'employee')->when($this->department !== '' && $this->department !== '__unassigned__', fn ($q) => $q->whereHas('employeeProfile', fn ($q) => $q->where('department', $this->department)))->orderBy('name')->get(['id', 'name']),
            'departments' => EmployeeProfile::query()->whereHas('user', fn ($q) => $q->where('role', 'employee'))->whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department')];
    }
}
