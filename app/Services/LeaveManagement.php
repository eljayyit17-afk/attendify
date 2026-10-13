<?php

namespace App\Services;

use App\Models\LeaveAudit;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LeaveManagement
{
    public function request(string $type, string $from, string $to, string $reason): LeaveRequest
    {
        try {
            return DB::transaction(function () use ($type, $from, $to, $reason) {
                $employee = $this->actor();
                Gate::forUser($employee)->authorize('create', LeaveRequest::class);
                $data = Validator::make(['type' => $type, 'from' => $from, 'to' => $to, 'reason' => trim($reason)], [
                    'type' => 'required|in:VL,SL', 'from' => 'required|date_format:Y-m-d',
                    'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'reason' => 'required|string|min:10|max:500',
                ])->validate();
                $first = CarbonImmutable::parse($from, 'UTC');
                $end = CarbonImmutable::parse($to, 'UTC');
                if ($first->diffInDays($end) > 89 || $first->lt(CarbonImmutable::now('Asia/Manila')->startOfDay()->subYear()) || $end->gt(CarbonImmutable::now('Asia/Manila')->startOfDay()->addYear())) {
                    throw ValidationException::withMessages(['from' => 'Choose up to 90 full days within one year before or after today.']);
                }
                $profile = $employee->employeeProfile;
                if (! $profile) {
                    throw ValidationException::withMessages(['reason' => 'Your employee profile must be configured before requesting leave.']);
                }
                if (DB::table('leave_reservations')->where('user_id', $employee->id)->whereBetween('work_date', [$from, $to])->exists()) {
                    throw ValidationException::withMessages(['from' => 'These dates overlap a pending or approved leave request.']);
                }
                $leave = LeaveRequest::create(['user_id' => $employee->id, 'employee_id' => $profile->employee_id,
                    'leave_type' => $type, 'start_date' => $from, 'end_date' => $to, 'reason' => $data['reason'], 'status' => 'pending']);
                $days = [];
                for ($date = $first; $date->lte($end); $date = $date->addDay()) {
                    $days[] = ['leave_request_id' => $leave->id, 'user_id' => $employee->id, 'work_date' => $date->toDateString()];
                }
                DB::table('leave_reservations')->insert($days);

                return $leave;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['from' => 'These dates overlap an existing request. Refresh and choose different dates.']);
        }
    }

    public function decide(int $id, string $decision, string $remarks = ''): void
    {
        DB::transaction(function () use ($id, $decision, $remarks) {
            $actor = $this->actor();
            $leave = LeaveRequest::findOrFail($id);
            Gate::forUser($actor)->authorize('decide', $leave);
            User::whereKey($leave->user_id)->lockForUpdate()->firstOrFail();
            $leave = LeaveRequest::lockForUpdate()->findOrFail($id);
            Validator::make(['decision' => $decision, 'remarks' => $remarks], ['decision' => 'required|in:approved,rejected', 'remarks' => 'nullable|string|max:500'])->validate();
            if ($leave->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This request has already been decided or cancelled.']);
            }
            if ($decision === 'approved' && $leave->user->attendanceSessions()->whereBetween('work_date', [$leave->start_date, $leave->end_date])->exists()) {
                throw ValidationException::withMessages(['decision' => 'Attendance exists in this date range. Resolve the conflict before approving full-day leave.']);
            }
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $leave->update(['status' => $decision, 'approver_id' => $actor->id, 'decided_at' => $now, 'remarks' => trim($remarks) ?: null]);
            LeaveAudit::create(['leave_request_id' => $id, 'actor_id' => $actor->id, 'from_status' => 'pending', 'to_status' => $decision, 'remarks' => trim($remarks) ?: null, 'created_at' => $now]);
            if ($decision === 'rejected') {
                DB::table('leave_reservations')->where('leave_request_id', $id)->delete();
            }
        }, 5);
    }

    public function cancel(int $id): void
    {
        DB::transaction(function () use ($id) {
            $actor = $this->actor();
            $leave = LeaveRequest::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('cancel', $leave);
            if (! in_array($leave->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages(['decision' => 'Only pending or future approved requests can be cancelled.']);
            }
            if ($leave->status === 'approved') {
                $window = app(WorkdayReport::class)->window($actor->scheduleAssignments()->get(), $leave->start_date);
                $start = $window['start'] ?? CarbonImmutable::parse($leave->start_date, 'Asia/Manila');
                if ($start->lte(CarbonImmutable::now('UTC'))) {
                    throw ValidationException::withMessages(['decision' => 'Approved leave that has started cannot be cancelled. Contact your administrator.']);
                }
            }
            $before = $leave->status;
            $leave->update(['status' => 'cancelled']);
            DB::table('leave_reservations')->where('leave_request_id', $id)->delete();
            LeaveAudit::create(['leave_request_id' => $id, 'actor_id' => $actor->id, 'from_status' => $before, 'to_status' => 'cancelled', 'created_at' => CarbonImmutable::now('UTC')]);
        }, 5);
    }

    private function actor(): User
    {
        abort_unless(Auth::id() !== null, 403);

        return User::lockForUpdate()->findOrFail(Auth::id());
    }
}
