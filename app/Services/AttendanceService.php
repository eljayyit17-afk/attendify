<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private ShiftRules $rules) {}

    public function clockIn(): AttendanceSession
    {
        try {
            return DB::transaction(function () {
                $employee = $this->actor();
                Gate::forUser($employee)->authorize('create', AttendanceSession::class);
                if ($employee->attendanceSessions()->whereNull('clock_out_at')->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'You already have an open attendance session. Clock out first, or ask your administrator to correct an incomplete record.']);
                }
                $profile = $employee->employeeProfile()->lockForUpdate()->first();
                $schedule = $profile?->schedule_id ? Schedule::query()->lockForUpdate()->find($profile->schedule_id) : null;
                if (! $schedule?->isConfigured()) {
                    throw ValidationException::withMessages(['attendance' => 'Your work schedule is not configured. Contact your administrator before clocking in.']);
                }
                $now = CarbonImmutable::now('UTC')->startOfSecond();
                $window = $this->rules->clockInWindow($schedule, $now);
                if (! $window) {
                    throw ValidationException::withMessages(['attendance' => 'Clock-in is unavailable on rest days or outside your shift window. You can clock in from one hour before your shift until its scheduled end.']);
                }
                if ($employee->attendanceSessions()->where('work_date', $window->workDate)->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'Attendance has already been recorded for this work date. Contact your administrator if it needs a correction.']);
                }

                if (Holiday::where('holiday_date', $window->workDate)->where('timezone', $schedule->timezone)->exists()
                    || $employee->leaveRequests()->where('status', 'approved')->where('start_date', '<=', $window->workDate)->where('end_date', '>=', $window->workDate)->exists()) {
                    throw ValidationException::withMessages(['attendance' => 'Clock-in is unavailable on an applicable holiday or approved full-day leave.']);
                }

                return $employee->attendanceSessions()->create([
                    'work_date' => $window->workDate, 'schedule_name' => $schedule->name,
                    'schedule_timezone' => $schedule->timezone,
                    'scheduled_start_at' => $window->start, 'scheduled_end_at' => $window->end,
                    'clock_in_at' => $now, 'version' => 1,
                ]);
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['attendance' => 'Attendance has already been recorded. Refresh the page to see the current session.']);
        }
    }

    public function clockOut(): AttendanceSession
    {
        return DB::transaction(function () {
            $employee = $this->actor();
            Gate::forUser($employee)->authorize('create', AttendanceSession::class);
            $session = $employee->attendanceSessions()->whereNull('clock_out_at')->lockForUpdate()->first();
            if (! $session) {
                throw ValidationException::withMessages(['attendance' => 'There is no open attendance session to clock out.']);
            }
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            if ($now->lessThan($session->clock_in_at)) {
                throw ValidationException::withMessages(['attendance' => 'The server clock is earlier than your clock-in. Contact your administrator.']);
            }
            if ($session->durationSeconds($now) > 86400) {
                throw ValidationException::withMessages(['attendance' => 'This attendance session has been open for more than 24 hours. Ask your administrator to correct the record.']);
            }
            $session->update(['clock_out_at' => $now, 'version' => $session->version + 1]);

            return $session->refresh();
        }, 5);
    }

    public function correct(int $id, string $clockInLocal, ?string $clockOutLocal, string $reason, int $expectedVersion): AttendanceSession
    {
        return DB::transaction(function () use ($id, $clockInLocal, $clockOutLocal, $reason, $expectedVersion) {
            $actor = $this->actor();
            $record = AttendanceSession::query()->findOrFail($id);
            Gate::forUser($actor)->authorize('correct', $record);
            // Use the same lock order as employee mutations and account management.
            User::query()->whereKey($record->user_id)->lockForUpdate()->firstOrFail();
            $record = AttendanceSession::query()->lockForUpdate()->findOrFail($id);
            if ($record->version !== $expectedVersion) {
                throw ValidationException::withMessages(['correction' => 'This record changed while you were editing. Close and reopen it before saving.']);
            }
            $data = Validator::make([
                'clockInLocal' => $clockInLocal, 'clockOutLocal' => $clockOutLocal ?: null, 'reason' => trim($reason),
            ], [
                'clockInLocal' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
                'clockOutLocal' => ['nullable', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
                'reason' => ['required', 'string', 'min:10', 'max:1000'],
            ])->validate();
            $in = CarbonImmutable::parse($data['clockInLocal'], 'Asia/Manila')->utc();
            $out = $data['clockOutLocal'] ? CarbonImmutable::parse($data['clockOutLocal'], 'Asia/Manila')->utc() : null;
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            if ($in->greaterThan($now) || $in->lessThan($record->scheduled_start_at->subHour()) || $in->greaterThanOrEqualTo($record->scheduled_end_at)) {
                throw ValidationException::withMessages(['clockInLocal' => 'Clock-in must be in this record’s original shift window and cannot be in the future.']);
            }
            if ($out && ($out->lessThan($in) || $out->greaterThan($now) || $out->getTimestamp() - $in->getTimestamp() > 86400)) {
                throw ValidationException::withMessages(['clockOutLocal' => 'Clock-out must follow clock-in, span no more than 24 hours, and cannot be in the future.']);
            }
            if (! $out && $now->getTimestamp() - $in->getTimestamp() > 86400) {
                throw ValidationException::withMessages(['clockOutLocal' => 'An older record must include its actual clock-out time.']);
            }
            $before = $this->snapshot($record);
            if ($record->clock_in_at->equalTo($in) && (($out === null && $record->clock_out_at === null) || ($out && $record->clock_out_at?->equalTo($out)))) {
                throw ValidationException::withMessages(['correction' => 'No attendance times have changed.']);
            }
            try {
                $record->update(['clock_in_at' => $in, 'clock_out_at' => $out, 'version' => $record->version + 1]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['correction' => 'This employee already has another open session. Close that session before reopening this record.']);
            }
            $record->corrections()->create([
                'actor_id' => $actor->id, 'reason' => $data['reason'],
                'before_values' => $before, 'after_values' => $this->snapshot($record),
                'created_at' => $now,
            ]);

            return $record->refresh();
        }, 5);
    }

    private function actor(): User
    {
        abort_unless(Auth::id() !== null, 403);

        return User::query()->lockForUpdate()->findOrFail(Auth::id());
    }

    /** @return array<string, int|string|null> */
    private function snapshot(AttendanceSession $record): array
    {
        return [
            'clock_in_at' => $record->clock_in_at->toIso8601String(),
            'clock_out_at' => $record->clock_out_at?->toIso8601String(),
            'work_date' => $record->work_date, 'version' => $record->version,
        ];
    }
}
