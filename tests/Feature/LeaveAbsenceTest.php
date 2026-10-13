<?php

use App\Models\Holiday;
use App\Models\LeaveAudit;
use App\Models\LeaveRequest;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LeaveManagement;
use App\Services\WorkdayReport;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function leaveEmployee(array $scheduleData = []): User
{
    $employee = User::factory()->create(['role' => 'employee', 'requires_password_setup' => false]);
    $schedule = Schedule::create(array_replace(['name' => 'Leave shift '.$employee->id, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'timezone' => 'Asia/Manila', 'working_days' => [1, 2, 3, 4, 5]], $scheduleData));
    $employee->employeeProfile()->create(['employee_id' => 'EMP-'.$employee->id, 'hire_date' => '2026-10-01', 'schedule_id' => $schedule->id]);

    return $employee;
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 00:00:00', 'UTC'));
});

it('submits leave through the portal and records an audited approval with UTC decision time', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    Livewire::test('employee.leave')->set('type', 'VL')->set('from', '2026-10-14')->set('to', '2026-10-15')->set('reason', 'Personal family commitments.')
        ->call('submit')->assertHasNoErrors();
    $leave = LeaveRequest::sole();
    expect($leave->user_id)->toBe($employee->id)->and($leave->status)->toBe('pending')
        ->and(DB::table('leave_reservations')->count())->toBe(2);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    Livewire::test('admin.leave')->call('review', $leave->id)->set('remarks', 'Coverage confirmed.')->call('decide', 'approved')->assertHasNoErrors();
    expect($leave->refresh()->status)->toBe('approved')->and($leave->approver_id)->toBe($admin->id)
        ->and($leave->getRawOriginal('decided_at'))->toBe('2026-10-13 00:00:00');
    $audit = LeaveAudit::sole();
    expect($audit->from_status)->toBe('pending')->and($audit->to_status)->toBe('approved');
    expect(fn () => $audit->update(['remarks' => 'Changed']))->toThrow(LogicException::class);
    expect(fn () => DB::table('leave_audits')->where('id', $audit->id)->delete())->toThrow(QueryException::class);
});

it('prevents overlapping types and duplicate submissions while allowing adjacent dates', function () {
    $this->actingAs(leaveEmployee());
    $service = app(LeaveManagement::class);
    $service->request('VL', '2026-10-14', '2026-10-15', 'Personal family commitments.');
    expect(fn () => $service->request('SL', '2026-10-15', '2026-10-16', 'Unable to attend work today.'))->toThrow(ValidationException::class);
    expect(fn () => $service->request('VL', '2026-10-14', '2026-10-15', 'Personal family commitments.'))->toThrow(ValidationException::class);
    $service->request('VL', '2026-10-16', '2026-10-16', 'Personal family commitments.');
    expect(LeaveRequest::count())->toBe(2);
});

it('enforces overlap reservations at the database level', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $leave = app(LeaveManagement::class)->request('VL', '2026-10-14', '2026-10-14', 'Personal family commitments.');
    expect(fn () => DB::table('leave_reservations')->insert(['leave_request_id' => $leave->id, 'user_id' => $employee->id, 'work_date' => '2026-10-14']))->toThrow(UniqueConstraintViolationException::class);
});

it('releases reservations after rejection or cancellation but preserves request history', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $service = app(LeaveManagement::class);
    $leave = $service->request('SL', '2026-10-14', '2026-10-14', 'Unable to attend work today.');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $service->decide($leave->id, 'rejected', 'Please choose another day.');
    $this->actingAs($employee);
    $next = $service->request('VL', '2026-10-14', '2026-10-14', 'Personal family commitments.');
    $service->cancel($next->id);
    expect($leave->refresh()->status)->toBe('rejected')->and($next->refresh()->status)->toBe('cancelled')
        ->and(DB::table('leave_reservations')->count())->toBe(0)->and(LeaveAudit::count())->toBe(2);
});

it('permits future approved cancellation and rejects cancellation after its shift starts', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $service = app(LeaveManagement::class);
    $leave = $service->request('VL', '2026-10-14', '2026-10-14', 'Personal family commitments.');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $service->decide($leave->id, 'approved');
    $this->actingAs($employee);
    $service->cancel($leave->id);
    $next = $service->request('VL', '2026-10-14', '2026-10-14', 'Personal family commitments.');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $service->decide($next->id, 'approved');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 01:00:00', 'UTC'));
    $this->actingAs($employee);
    expect(fn () => $service->cancel($next->id))->toThrow(ValidationException::class);
});

it('rejects malformed ranges partial-day values excessive ranges and unsupported leave types', function ($type, $from, $to, $reason) {
    $this->actingAs(leaveEmployee());
    expect(fn () => app(LeaveManagement::class)->request($type, $from, $to, $reason))->toThrow(ValidationException::class);
    expect(LeaveRequest::count())->toBe(0);
})->with([
    ['PL', '2026-10-14', '2026-10-14', 'Personal family commitments.'],
    ['VL', '2026-10-15', '2026-10-14', 'Personal family commitments.'],
    ['SL', '2026-10-14T09:00', '2026-10-14', 'Unable to attend work today.'],
    ['VL', '2026-10-14', '2027-03-01', 'Personal family commitments.'],
    ['VL', '2026-10-14', '2026-10-14', ''],
]);

it('calculates absence only after shift end and never for rest or unscheduled days', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $report = app(WorkdayReport::class);
    expect($report->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Not due');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 08:59:59', 'UTC'));
    expect($report->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Not due');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:00:00', 'UTC'));
    expect($report->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Absent');
    expect($report->rows('2026-10-17', '2026-10-17')->sole()['status'])->toBe('Rest day');
    expect($report->rows('2026-10-12', '2026-10-12')->sole()['status'])->toBe('Unscheduled');
});

it('reclassifies a completed-shift absence after retroactive approved leave', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00', 'UTC'));
    $leave = app(LeaveManagement::class)->request('SL', '2026-10-13', '2026-10-13', 'Unable to attend work today.');
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Absent');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    app(LeaveManagement::class)->decide($leave->id, 'approved');
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13', $employee->id)->sole()['status'])->toBe('SL');
});

it('excludes applicable holidays and rest days from approved leave classifications', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $leave = app(LeaveManagement::class)->request('VL', '2026-10-16', '2026-10-18', 'Personal family commitments.');
    Holiday::create(['name' => 'Configured holiday', 'holiday_date' => '2026-10-16', 'timezone' => 'Asia/Manila']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    app(LeaveManagement::class)->decide($leave->id, 'approved');
    $rows = app(WorkdayReport::class)->rows('2026-10-16', '2026-10-18', $employee->id);
    expect($rows->pluck('status')->all())->toBe(['Holiday', 'Rest day', 'Rest day']);
});

it('uses the shift-start date timezone and overnight end for absence', function () {
    $employee = leaveEmployee(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
    $this->actingAs($employee);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 21:59:59', 'UTC'));
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Not due');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 22:00:00', 'UTC'));
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Absent');
});

it('preserves past schedule rules after edits and excludes inactive periods', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 00:00:00', 'UTC'));
    $employee->employeeProfile->schedule->update(['working_days' => [3], 'start_time' => '10:00:00']);
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Absent');
    $employee->forceFill(['is_active' => false])->save();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    expect(app(WorkdayReport::class)->rows('2026-10-14', '2026-10-14', $employee->id)->sole()['status'])->toBe('Unscheduled');
});

it('classifies late attendance from the original shift snapshot and rejects approval conflicts', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $leave = app(LeaveManagement::class)->request('VL', '2026-10-13', '2026-10-13', 'Personal family commitments.');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 01:00:01', 'UTC'));
    app(AttendanceService::class)->clockIn();
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Late');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    expect(fn () => app(LeaveManagement::class)->decide($leave->id, 'approved'))->toThrow(ValidationException::class);
    expect($leave->refresh()->status)->toBe('pending')->and(LeaveAudit::count())->toBe(0);
});

it('blocks clock-in on approved leave or an applicable holiday', function ($holiday) {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    if ($holiday) {
        Holiday::create(['name' => 'Configured holiday', 'holiday_date' => '2026-10-13', 'timezone' => 'Asia/Manila']);
    } else {
        $leave = app(LeaveManagement::class)->request('VL', '2026-10-13', '2026-10-13', 'Personal family commitments.');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        app(LeaveManagement::class)->decide($leave->id, 'approved');
        $this->actingAs($employee);
    }
    expect(fn () => app(AttendanceService::class)->clockIn())->toThrow(ValidationException::class);
})->with([true, false]);

it('isolates employee leave calendar and reporting and rejects unauthorized decisions', function () {
    $other = leaveEmployee();
    $this->actingAs($other);
    $leave = app(LeaveManagement::class)->request('SL', '2026-10-14', '2026-10-14', 'Unable to attend work today.');
    $employee = leaveEmployee();
    $this->actingAs($employee);
    Livewire::test('employee.leave')->assertViewHas('requests', fn ($requests) => $requests->total() === 0);
    expect(fn () => app(LeaveManagement::class)->cancel($leave->id))->toThrow(AuthorizationException::class);
    expect(fn () => app(LeaveManagement::class)->decide($leave->id, 'approved'))->toThrow(AuthorizationException::class);
    expect(fn () => app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13', $other->id))->toThrow(HttpException::class);
    foreach (['leave', 'reports', 'holidays', 'workdays'] as $page) {
        $this->get(route('admin.'.$page))->assertForbidden();
    }
    $this->get(route('employee.calendar'))->assertOk();
});

it('rejects decisions after the request has already changed', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $leave = app(LeaveManagement::class)->request('VL', '2026-10-14', '2026-10-14', 'Personal family commitments.');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    app(LeaveManagement::class)->decide($leave->id, 'approved');
    expect(fn () => app(LeaveManagement::class)->decide($leave->id, 'rejected'))->toThrow(ValidationException::class);
    expect(LeaveAudit::count())->toBe(1);
});

it('filters reports and dashboard statistics using actual workdays', function () {
    $employee = leaveEmployee();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00', 'UTC'));
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    Livewire::test('admin.reports')->set('from', '2026-10-13')->set('to', '2026-10-13')->set('status', 'Absent')->set('employeeId', (string) $employee->id)
        ->assertViewHas('rows', fn ($rows) => $rows->total() === 1)->assertViewHas('counts', fn ($counts) => $counts['Absent'] === 1);
    Livewire::test('admin.dashboard')->assertViewHas('workdayCounts', fn ($counts) => $counts['Absent'] === 1);
    foreach (['leave', 'reports', 'holidays', 'workdays'] as $page) {
        $this->get(route('admin.'.$page))->assertOk();
    }
});

it('rejects inactive employees and administrators on every leave mutation', function () {
    $employee = leaveEmployee();
    $this->actingAs($employee);
    $leave = app(LeaveManagement::class)->request('VL', '2026-10-14', '2026-10-14', 'Personal family commitments.');
    $employee->forceFill(['is_active' => false])->save();
    expect(fn () => app(LeaveManagement::class)->cancel($leave->id))->toThrow(AuthorizationException::class);
    expect(fn () => app(LeaveManagement::class)->request('SL', '2026-10-15', '2026-10-15', 'Unable to attend work today.'))->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]));
    expect(fn () => app(LeaveManagement::class)->decide($leave->id, 'approved'))->toThrow(AuthorizationException::class);
});

it('exports only authorized filtered report records without including leave reasons', function () {
    $employee = leaveEmployee();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00', 'UTC'));
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    Livewire::test('admin.reports')->set('from', '2026-10-13')->set('to', '2026-10-13')
        ->set('status', 'Absent')->set('employeeId', (string) $employee->id)->call('exportCsv')
        ->assertFileDownloaded('attendify-workdays-2026-10-13-2026-10-13.csv');
});

it('reserves an inclusive leap-day range without skipping or duplicating dates', function () {
    $this->travelTo(CarbonImmutable::parse('2028-02-28 00:00:00', 'UTC'));
    $employee = leaveEmployee();
    $this->actingAs($employee);
    app(LeaveManagement::class)->request('VL', '2028-02-28', '2028-03-01', 'Personal family commitments.');
    expect(DB::table('leave_reservations')->orderBy('work_date')->pluck('work_date')->all())
        ->toBe(['2028-02-28', '2028-02-29', '2028-03-01']);
});

it('uses a non-Manila overnight schedule and only its matching holiday timezone', function () {
    $employee = leaveEmployee(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'timezone' => 'America/New_York']);
    $this->actingAs($employee);
    Holiday::create(['name' => 'Manila-only holiday', 'holiday_date' => '2026-10-13', 'timezone' => 'Asia/Manila']);
    $this->travelTo(CarbonImmutable::parse('2026-10-14 09:59:59', 'UTC'));
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Not due');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00', 'UTC'));
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Absent');
    Holiday::create(['name' => 'Matching holiday', 'holiday_date' => '2026-10-13', 'timezone' => 'America/New_York']);
    expect(app(WorkdayReport::class)->rows('2026-10-13', '2026-10-13')->sole()['status'])->toBe('Holiday');
});
