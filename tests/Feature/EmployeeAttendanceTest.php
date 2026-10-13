<?php

use App\Models\AttendanceCorrection;
use App\Models\AttendanceSession;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function attendanceEmployee(array $scheduleData = [], array $userData = []): User
{
    $schedule = Schedule::create(array_replace([
        'name' => 'Shift '.str()->uuid(), 'start_time' => '09:00:00', 'end_time' => '17:00:00',
        'timezone' => 'Asia/Manila', 'working_days' => [1, 2, 3, 4, 5, 6, 7],
    ], $scheduleData));
    $employee = User::factory()->create(array_replace(['role' => 'employee', 'requires_password_setup' => false], $userData));
    $employee->employeeProfile()->create([
        'employee_id' => 'EMP-'.$employee->id, 'department' => 'Operations', 'position' => 'Associate',
        'hire_date' => '2026-10-01', 'schedule_id' => $schedule->id,
    ]);

    return $employee;
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 00:30:00', 'UTC'));
});

it('records server UTC times and finalizes exact working duration', function () {
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    $component = Livewire::test('employee.dashboard')->call('clockIn')->assertHasNoErrors()->assertSee('Clock-in recorded.');
    $record = AttendanceSession::sole();
    expect($record->user_id)->toBe($employee->id)->and($record->work_date)->toBe('2026-10-13')
        ->and($record->getRawOriginal('clock_in_at'))->toBe('2026-10-13 00:30:00');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:15:30', 'UTC'));
    $component->call('clockOut')->assertHasNoErrors()->assertSee('8h 45m 30s');
    expect($record->refresh()->durationSeconds())->toBe(31530)
        ->and($record->clock_out_at->timezoneName)->toBe('UTC');
});

it('rejects duplicate clock-ins and clock-outs', function () {
    $this->actingAs(attendanceEmployee());
    $component = Livewire::test('employee.dashboard')->call('clockIn')->assertHasNoErrors();
    $component->call('clockIn')->assertHasErrors('attendance');
    $component->call('clockOut')->assertHasNoErrors();
    $component->call('clockOut')->assertHasErrors('attendance');
    $component->call('clockIn')->assertHasErrors('attendance');
    expect(AttendanceSession::count())->toBe(1);
});

it('rejects clock-out without an active session', function () {
    $this->actingAs(attendanceEmployee());
    Livewire::test('employee.dashboard')->call('clockOut')->assertHasErrors('attendance');
    expect(AttendanceSession::count())->toBe(0);
});

it('enforces unique work dates and a single open session at the database level', function () {
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    $record = app(AttendanceService::class)->clockIn();
    $data = $record->only(['user_id', 'work_date', 'schedule_name', 'schedule_timezone', 'scheduled_start_at', 'scheduled_end_at', 'clock_in_at', 'clock_out_at']);
    expect(fn () => AttendanceSession::create(array_replace($data, ['work_date' => '2026-10-14'])))
        ->toThrow(UniqueConstraintViolationException::class);
    $record = app(AttendanceService::class)->clockOut();
    $data['clock_out_at'] = $record->clock_out_at;
    expect(fn () => AttendanceSession::create($data))->toThrow(UniqueConstraintViolationException::class);
    expect(AttendanceSession::count())->toBe(1);
});

it('permits independent active sessions for different employees', function () {
    $this->actingAs(attendanceEmployee());
    app(AttendanceService::class)->clockIn();
    $this->actingAs(attendanceEmployee());
    app(AttendanceService::class)->clockIn();
    expect(AttendanceSession::whereNull('clock_out_at')->count())->toBe(2);
});

it('does not accept client identity or time as authoritative', function () {
    $other = attendanceEmployee();
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    Livewire::withQueryParams(['employeeId' => $other->id, 'timestamp' => '2000-01-01 00:00:00'])
        ->test('employee.dashboard')->call('clockIn')->assertHasNoErrors();
    $record = AttendanceSession::sole();
    expect($record->user_id)->toBe($employee->id)
        ->and($record->getRawOriginal('clock_in_at'))->toBe('2026-10-13 00:30:00');
});

it('isolates personal history and profile information', function () {
    $other = attendanceEmployee(['name' => 'Private shift'], ['name' => 'Other employee']);
    $this->actingAs($other);
    $otherRecord = app(AttendanceService::class)->clockIn();
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    app(AttendanceService::class)->clockIn();
    Livewire::test('employee.attendance')->assertDontSee('Private shift')
        ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->user_id === $employee->id);
    $this->get(route('employee.profile'))->assertOk()->assertDontSee('Other employee');
    expect(Gate::allows('view', $otherRecord))->toBeFalse();
    expect(fn () => app(AttendanceService::class)->correct($otherRecord->id, '2026-10-13T09:00:00', '2026-10-13T17:00:00', 'Attempting another employee correction', 1))
        ->toThrow(AuthorizationException::class);
});

it('redirects employees to a working portal and keeps all admin routes forbidden', function () {
    $employee = attendanceEmployee();
    $this->actingAs($employee)->get(route('dashboard'))->assertRedirect(route('employee.dashboard'));
    foreach (['dashboard', 'attendance', 'profile', 'schedule'] as $page) {
        $this->get(route('employee.'.$page))->assertOk();
    }
    foreach (['dashboard', 'employees', 'attendance', 'schedules', 'reports'] as $page) {
        $this->get(route('admin.'.$page))->assertForbidden();
    }
    Livewire::test('admin.attendance')->assertForbidden();
    Livewire::test('admin.schedules')->assertForbidden();
});

it('rejects guests admins inactive and pending accounts on clock mutations', function ($attributes) {
    $employee = attendanceEmployee([], $attributes);
    $this->actingAs($employee);
    Livewire::test('employee.dashboard')->assertForbidden();
    expect(fn () => app(AttendanceService::class)->clockIn())->toThrow(AuthorizationException::class);
    expect(fn () => app(AttendanceService::class)->clockOut())->toThrow(AuthorizationException::class);
    expect(AttendanceSession::count())->toBe(0);
})->with([[['role' => 'admin']], [['is_active' => false]], [['requires_password_setup' => true]]]);

it('rejects unauthenticated clock actions and redirects guest portal pages', function () {
    foreach (['dashboard', 'attendance', 'profile', 'schedule'] as $page) {
        $this->get(route('employee.'.$page))->assertRedirect(route('login'));
    }
    expect(fn () => app(AttendanceService::class)->clockIn())->toThrow(HttpException::class);
});

it('rechecks account permissions after a portal has already been opened', function () {
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    $component = Livewire::test('employee.dashboard');
    User::whereKey($employee->id)->update(['is_active' => false]);
    expect(fn () => app(AttendanceService::class)->clockIn())->toThrow(AuthorizationException::class);
    $employee->refresh();
    $component->call('clockIn')->assertForbidden();
    expect(AttendanceSession::count())->toBe(0);
});

it('requires an assigned fully configured schedule', function ($missingProfile) {
    $employee = attendanceEmployee(['start_time' => null]);
    if ($missingProfile) {
        $employee->employeeProfile()->delete();
    }
    $this->actingAs($employee);
    Livewire::test('employee.dashboard')->call('clockIn')->assertHasErrors('attendance');
    expect(AttendanceSession::count())->toBe(0);
})->with([true, false]);

it('rejects rest days and times outside the clock-in window', function ($utc, $days) {
    $employee = attendanceEmployee(['working_days' => $days]);
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    $this->actingAs($employee);
    Livewire::test('employee.dashboard')->call('clockIn')->assertHasErrors('attendance');
})->with([
    ['2026-10-12 23:59:59', [1, 2, 3, 4, 5]],
    ['2026-10-13 09:00:00', [1, 2, 3, 4, 5]],
    ['2026-10-13 01:00:00', [1]],
]);

it('accepts the exact early clock-in boundary and calculates the local work date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 00:00:00', 'UTC'));
    $this->actingAs(attendanceEmployee());
    expect(app(AttendanceService::class)->clockIn()->work_date)->toBe('2026-10-13');
});

it('assigns after-midnight clock-ins to the previous overnight shift including a rest-day morning', function () {
    $this->actingAs(attendanceEmployee(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'working_days' => [2]]));
    $this->travelTo(CarbonImmutable::parse('2026-10-13 17:30:00', 'UTC'));
    $record = app(AttendanceService::class)->clockIn();
    expect($record->work_date)->toBe('2026-10-13');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 23:15:00', 'UTC'));
    expect(app(AttendanceService::class)->clockOut()->durationSeconds())->toBe(20700);
});

it('assigns early pre-midnight clock-ins to the upcoming shift-start date', function () {
    $this->actingAs(attendanceEmployee(['start_time' => '00:00:00', 'end_time' => '08:00:00', 'working_days' => [3]]));
    $this->travelTo(CarbonImmutable::parse('2026-10-13 15:30:00', 'UTC'));
    expect(app(AttendanceService::class)->clockIn()->work_date)->toBe('2026-10-14');
});

it('calculates actual elapsed seconds across daylight saving boundaries', function ($in, $out, $seconds, $date) {
    $this->actingAs(attendanceEmployee(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'timezone' => 'America/New_York']));
    $this->travelTo(CarbonImmutable::parse($in, 'UTC'));
    expect(app(AttendanceService::class)->clockIn()->work_date)->toBe($date);
    $this->travelTo(CarbonImmutable::parse($out, 'UTC'));
    expect(app(AttendanceService::class)->clockOut()->durationSeconds())->toBe($seconds);
})->with([
    ['2026-03-08 03:00:00', '2026-03-08 10:00:00', 25200, '2026-03-07'],
    ['2026-11-01 02:00:00', '2026-11-01 11:00:00', 32400, '2026-10-31'],
]);

it('requires correction for stale incomplete sessions and never assumes a clock-out', function () {
    $this->actingAs(attendanceEmployee());
    $record = app(AttendanceService::class)->clockIn();
    $this->travel(25)->hours();
    Livewire::test('employee.dashboard')->assertSee('Needs correction')->call('clockOut')->assertHasErrors('attendance')
        ->call('clockIn')->assertHasErrors('attendance');
    expect($record->refresh()->clock_out_at)->toBeNull()->and($record->durationLabel())->toBe('Not finalized');
});

it('handles date filters and pagination without exposing other records', function () {
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    $service = app(AttendanceService::class);
    for ($day = 1; $day <= 17; $day++) {
        $this->travelTo(CarbonImmutable::parse(sprintf('2026-10-%02d 01:00:00', $day), 'UTC'));
        $service->clockIn();
        $service->clockOut();
    }
    $component = Livewire::test('employee.attendance')->assertViewHas('records', fn ($records) => $records->total() === 17 && $records->count() === 15);
    $component->call('setPage', 2)->set('fromDate', '2026-10-03')->set('toDate', '2026-10-05')
        ->assertViewHas('records', fn ($records) => $records->total() === 3 && $records->currentPage() === 1);
    $component->set('toDate', '2026-10-01')->assertHasErrors('toDate')
        ->set('fromDate', 'bad-date')->assertHasErrors('fromDate');
    $component->call('resetFilters')->assertHasNoErrors()->assertViewHas('records', fn ($records) => $records->total() === 17);
});

it('uses UTC persistence even when the application timezone changes', function () {
    config(['app.timezone' => 'Asia/Manila']);
    $this->actingAs(attendanceEmployee());
    $record = app(AttendanceService::class)->clockIn();
    expect($record->getRawOriginal('clock_in_at'))->toBe('2026-10-13 00:30:00')
        ->and($record->clock_in_at->format('H:i'))->toBe('00:30');
    Livewire::test('employee.dashboard')->assertSee('8:30:00 AM');
});

it('retains historical shift details when the assigned schedule changes', function () {
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    $record = app(AttendanceService::class)->clockIn();
    $original = $record->schedule_name;
    $employee->employeeProfile->schedule->update(['name' => 'Changed shift', 'start_time' => '10:00:00']);
    expect($record->refresh()->schedule_name)->toBe($original)->and($record->scheduled_start_at->format('H:i'))->toBe('01:00');
    expect(app(AttendanceService::class)->clockOut()->durationSeconds())->toBe(0);
});

it('saves audited admin corrections atomically with the actor and original timestamps', function () {
    $this->actingAs(attendanceEmployee());
    $record = app(AttendanceService::class)->clockIn();
    $this->travel(25)->hours();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    Livewire::test('admin.attendance')->call('editRecord', $record->id)
        ->set('clockOutLocal', '2026-10-13T17:00:00')->set('reason', 'Employee forgot to clock out after finishing their shift.')
        ->call('saveCorrection')->assertHasNoErrors();
    $audit = AttendanceCorrection::sole();
    expect($record->refresh()->durationSeconds())->toBe(30600)
        ->and($audit->actor_id)->toBe($admin->id)->and($audit->before_values['clock_out_at'])->toBeNull()
        ->and($audit->after_values['clock_out_at'])->toBe('2026-10-13T09:00:00+00:00');
    expect(fn () => $audit->update(['reason' => 'Rewriting history']))->toThrow(LogicException::class);
    expect(fn () => DB::table('attendance_corrections')->where('id', $audit->id)->delete())->toThrow(QueryException::class);
});

it('rejects incomplete future invalid or unchanged corrections', function ($in, $out, $reason) {
    $this->actingAs(attendanceEmployee());
    $record = app(AttendanceService::class)->clockIn();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    expect(fn () => app(AttendanceService::class)->correct($record->id, $in, $out, $reason, 1))
        ->toThrow(ValidationException::class);
    expect(AttendanceCorrection::count())->toBe(0)->and($record->refresh()->version)->toBe(1);
})->with([
    ['2026-10-13T08:30:00', null, 'Short'],
    ['2026-10-13T08:30:00', '2026-10-13T08:00:00', 'Clock-out is before clock-in.'],
    ['2026-10-13T08:30:00', '2026-10-13T17:00:00', 'Future clock-out is invalid.'],
    ['2026-10-13T07:00:00', null, 'Clock-in is outside shift window.'],
    ['2026-10-13T08:30:00', null, 'There are no changed timestamps.'],
]);

it('rejects stale correction versions and unauthorized admin changes', function () {
    $this->actingAs(attendanceEmployee());
    $record = app(AttendanceService::class)->clockIn();
    $this->travel(1)->hours();
    app(AttendanceService::class)->clockOut();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    expect(fn () => app(AttendanceService::class)->correct($record->id, '2026-10-13T09:00:00', '2026-10-13T09:30:00', 'A correction to clock-in time.', 1))
        ->toThrow(ValidationException::class);
    $admin->forceFill(['is_active' => false])->save();
    expect(fn () => app(AttendanceService::class)->correct($record->id, '2026-10-13T09:00:00', '2026-10-13T09:30:00', 'A correction to clock-in time.', 2))
        ->toThrow(AuthorizationException::class);
    expect(AttendanceCorrection::count())->toBe(0);
});

it('configures schedules through protected admin controls', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $component = Livewire::test('admin.schedules')->call('createSchedule')->set('name', 'Night shift')
        ->set('startTime', '22:00')->set('endTime', '06:00')->set('workingDays', ['1', '2', '3', '4', '5'])
        ->call('saveSchedule')->assertHasNoErrors();
    $schedule = Schedule::sole();
    expect($schedule->isConfigured())->toBeTrue()->and($schedule->working_days)->toBe([1, 2, 3, 4, 5]);
    $component->call('editSchedule', $schedule->id)->set('endTime', '22:00')->call('saveSchedule')->assertHasErrors('endTime');
    $component->set('endTime', '06:00')->set('timezone', 'Invalid/Zone')->call('saveSchedule')->assertHasErrors('timezone');
});

it('prevents account deletion from erasing attendance records while keeping the session intact', function () {
    $employee = attendanceEmployee();
    $this->actingAs($employee);
    app(AttendanceService::class)->clockIn();
    Livewire::test('pages::settings.delete-user-modal')->set('password', 'password')->call('deleteUser')->assertHasErrors('account');
    expect($employee->fresh())->not->toBeNull()->and(AttendanceSession::count())->toBe(1);
    $this->assertAuthenticatedAs($employee);
});
