<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\ScheduleAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;

class WorkdayReport
{
    /** @return LazyCollection<int, array<string, mixed>> */
    public function rows(string $from, string $to, ?int $employeeId = null, ?string $department = null, int $maxDays = 31): LazyCollection
    {
        $actor = User::find(Auth::id());
        abort_unless($actor && $actor->is_active && ! $actor->requires_password_setup && in_array($actor->role, ['admin', 'employee'], true), 403);
        if ($actor->role === 'employee') {
            abort_unless($employeeId === null || $employeeId === $actor->id, 403);
            $employeeId = $actor->id;
        }
        Validator::make(['from' => $from, 'to' => $to], ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'])->validate();
        $first = CarbonImmutable::parse($from, 'UTC');
        $last = CarbonImmutable::parse($to, 'UTC');
        abort_if($maxDays < 1 || $maxDays > 366 || $first->diffInDays($last) >= $maxDays, 422, 'Choose at most '.$maxDays.' work dates.');
        $holidays = Holiday::whereBetween('holiday_date', [$from, $to])->get()->keyBy(fn ($holiday) => $holiday->holiday_date.'|'.$holiday->timezone);
        $employees = User::query()->where('role', 'employee')->when($employeeId, fn ($query) => $query->whereKey($employeeId))
            ->when($department !== null && $department !== '', function ($query) use ($department) {
                if ($department === '__unassigned__') {
                    $query->where(fn ($query) => $query->whereDoesntHave('employeeProfile')->orWhereHas('employeeProfile', fn ($profile) => $profile->where(fn ($profile) => $profile->whereNull('department')->orWhere('department', ''))));
                } else {
                    $query->whereHas('employeeProfile', fn ($profile) => $profile->where('department', $department));
                }
            })
            ->select(['id', 'name'])->with([
                'employeeProfile:id,user_id,hire_date,employee_id,department',
                'scheduleAssignments' => fn ($query) => $query->where('effective_from', '<=', $last->addDays(2)->toDateTimeString())
                    ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $first->subDays(2)->toDateTimeString())),
                'attendanceSessions' => fn ($query) => $query->whereBetween('work_date', [$from, $to]),
                'leaveRequests' => fn ($query) => $query->select(['id', 'user_id', 'start_date', 'end_date', 'status', 'leave_type'])
                    ->whereIn('status', ['pending', 'approved'])->where('start_date', '<=', $to)->where('end_date', '>=', $from),
            ])->orderBy('id')->lazyById(max(1, min(100, intdiv(3000, (int) $first->diffInDays($last) + 1))));
        $now = CarbonImmutable::now('UTC');

        return new LazyCollection(function () use ($employees, $first, $last, $holidays, $now) {
            foreach ($employees as $employee) {
                $sessions = $employee->attendanceSessions->keyBy('work_date');
                for ($date = $first; $date->lte($last); $date = $date->addDay()) {
                    $day = $date->toDateString();
                    $session = $sessions->get($day);
                    $window = $session ? null : $this->window($employee->scheduleAssignments, $day);
                    $leave = $employee->leaveRequests->first(fn ($leave) => $leave->start_date <= $day && $leave->end_date >= $day && $leave->status === 'approved');
                    $pending = $employee->leaveRequests->contains(fn ($leave) => $leave->start_date <= $day && $leave->end_date >= $day && $leave->status === 'pending');
                    $status = 'Unscheduled';
                    if ($session) {
                        $status = $session->clock_in_at->gt($session->scheduled_start_at) ? 'Late' : 'Present';
                    } elseif ($window && (! $employee->employeeProfile?->hire_date || $day >= $employee->employeeProfile->hire_date->toDateString())) {
                        $status = $holidays->has($day.'|'.$window['timezone']) ? 'Holiday'
                            : ($leave ? $leave->leave_type : ($now->gte($window['end']) ? 'Absent' : 'Not due'));
                    } elseif ($employee->scheduleAssignments->contains(fn ($assignment) => $assignment->effective_from->setTimezone($assignment->timezone)->toDateString() <= $day
                        && (! $assignment->effective_until || $assignment->effective_until->setTimezone($assignment->timezone)->toDateString() >= $day)
                        && ! in_array($date->isoWeekday(), $assignment->working_days, true))) {
                        $status = 'Rest day';
                    }
                    yield ['user_id' => $employee->id, 'employee' => $employee->name, 'employee_code' => $employee->employeeProfile->employee_id ?? null,
                        'department' => $employee->employeeProfile->department ?? 'Unassigned', 'date' => $day, 'status' => $status,
                        'pending' => $pending, 'session' => $session, 'timezone' => $session->schedule_timezone ?? ($window['timezone'] ?? null)];
                }
            }

        });
    }

    /** @param Collection<int, ScheduleAssignment> $assignments
     * @return array<string, mixed>|null
     */
    public function window(Collection $assignments, string $day): ?array
    {
        foreach ($assignments->sortBy('effective_from') as $assignment) {
            $date = CarbonImmutable::parse($day, $assignment->timezone);
            if (! in_array($date->isoWeekday(), $assignment->working_days, true)) {
                continue;
            }
            $start = CarbonImmutable::parse($day.' '.$assignment->start_time, $assignment->timezone)->utc();
            $endDay = $assignment->end_time <= $assignment->start_time ? $date->addDay()->toDateString() : $day;
            $end = CarbonImmutable::parse($endDay.' '.$assignment->end_time, $assignment->timezone)->utc();
            if ($start->gte($assignment->effective_from) && (! $assignment->effective_until || $start->lt($assignment->effective_until))) {
                return ['start' => $start, 'end' => $end, 'timezone' => $assignment->timezone];
            }
        }

        return null;
    }
}
