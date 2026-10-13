<?php

use App\Livewire\Admin\WorkdaySummary;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AttendanceAnalytics;
use App\Services\AttendancePdf;
use App\Services\AttendanceService;
use App\Services\LeaveManagement;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function analyticsEmployee(string $department = 'Operations', string $name = 'Morgan Rivera'): User
{
    $employee = User::factory()->create(['role' => 'employee', 'name' => $name, 'requires_password_setup' => false]);
    $schedule = Schedule::create(['name' => 'Analytics shift '.$employee->id, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'timezone' => 'Asia/Manila', 'working_days' => [1, 2, 3, 4, 5]]);
    $employee->employeeProfile()->create(['employee_id' => 'EMP-'.$employee->id, 'department' => $department, 'hire_date' => '2026-01-01', 'schedule_id' => $schedule->id]);

    return $employee;
}

function analyticsFilters(array $overrides = []): array
{
    return array_replace(['from' => '2026-10-13', 'to' => '2026-10-13', 'employeeId' => '', 'department' => '', 'status' => '', 'grouping' => 'daily'], $overrides);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 00:00:00', 'UTC'));
});

it('calculates actual statuses finalized hours incomplete sessions and explicit rates', function () {
    $onTime = analyticsEmployee();
    $late = analyticsEmployee('Finance', 'Casey Santos');
    analyticsEmployee('Finance', 'Absent employee');
    $vl = analyticsEmployee('Operations', 'Vacation employee');
    $sl = analyticsEmployee('Operations', 'Sick employee');
    $this->actingAs($onTime);
    app(AttendanceService::class)->clockIn();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 01:00:30', 'UTC'));
    $this->actingAs($late);
    app(AttendanceService::class)->clockIn();
    $this->actingAs($vl);
    $vlRequest = app(LeaveManagement::class)->request('VL', '2026-10-13', '2026-10-13', 'Personal family commitments.');
    $this->actingAs($sl);
    $slRequest = app(LeaveManagement::class)->request('SL', '2026-10-13', '2026-10-13', 'Unable to attend work today.');
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    app(LeaveManagement::class)->decide($vlRequest->id, 'approved');
    app(LeaveManagement::class)->decide($slRequest->id, 'approved');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:00:00', 'UTC'));
    $this->actingAs($onTime);
    app(AttendanceService::class)->clockOut();
    $this->actingAs($admin);
    $summary = app(AttendanceAnalytics::class)->summarize(analyticsFilters());
    expect($summary['counts'])->toMatchArray(['Present' => 1, 'Late' => 1, 'Absent' => 1, 'VL' => 1, 'SL' => 1])
        ->and($summary['finalizedSeconds'])->toBe(32400)->and($summary['incomplete'])->toBe(1)
        ->and($summary['attendanceRate'])->toBe(66.7)->and($summary['onTimeRate'])->toBe(50.0)
        ->and($summary['departmentCounts']['Finance'])->toMatchArray(['Late' => 1, 'Absent' => 1]);
});

it('groups daily weekly and monthly with complete requested buckets and ISO Monday weeks', function () {
    $employee = analyticsEmployee();
    $this->actingAs($employee);
    app(AttendanceService::class)->clockIn();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $filters = analyticsFilters(['from' => '2026-10-12', 'to' => '2026-11-02']);
    $daily = app(AttendanceAnalytics::class)->summarize($filters);
    expect(count($daily['periods']))->toBe(22)->and($daily['periods']['2026-10-13']['Present'])->toBe(1);
    $weekly = app(AttendanceAnalytics::class)->summarize(array_replace($filters, ['grouping' => 'weekly']));
    expect(array_keys($weekly['periods']))->toBe(['2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02']);
    $monthly = app(AttendanceAnalytics::class)->summarize(array_replace($filters, ['grouping' => 'monthly']));
    expect(array_keys($monthly['periods']))->toBe(['2026-10', '2026-11']);
});

it('applies employee department and status filters consistently to details and statistics', function () {
    $employee = analyticsEmployee('Finance');
    analyticsEmployee('Operations');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00', 'UTC'));
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $summary = app(AttendanceAnalytics::class)->summarize(analyticsFilters(['department' => 'Finance', 'status' => 'Absent', 'employeeId' => (string) $employee->id]));
    expect($summary['total'])->toBe(1)->and($summary['counts'])->toBe(['Absent' => 1])
        ->and(array_keys($summary['departmentCounts']))->toBe(['Finance'])->and($summary['details']->sole()['user_id'])->toBe($employee->id);
    Livewire::test('admin.reports')->set('employeeId', (string) $employee->id)->set('department', 'Operations')->assertSet('employeeId', '');
});

it('includes employees with no department or profile in the unassigned scope', function () {
    analyticsEmployee('');
    User::factory()->create(['role' => 'employee']);
    analyticsEmployee('Finance');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $summary = app(AttendanceAnalytics::class)->summarize(analyticsFilters(['department' => '__unassigned__']));
    expect($summary['employeeCount'])->toBe(2)->and(array_keys($summary['departmentCounts']))->toBe(['Unassigned']);
});

it('returns unavailable rates for untracked and empty scopes rather than invented percentages', function () {
    analyticsEmployee();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $summary = app(AttendanceAnalytics::class)->summarize(analyticsFilters(['from' => '2026-10-12', 'to' => '2026-10-12']));
    expect($summary['attendanceRate'])->toBeNull()->and($summary['onTimeRate'])->toBeNull()->and($summary['counts']['Unscheduled'])->toBe(1);
    $empty = app(AttendanceAnalytics::class)->summarize(analyticsFilters(['department' => 'No matching department']));
    expect($empty['total'])->toBe(0)->and($empty['attendanceRate'])->toBeNull();
});

it('preserves holiday rest day and leave exclusions in analytics denominators', function () {
    $employee = analyticsEmployee();
    $this->actingAs($employee);
    $leave = app(LeaveManagement::class)->request('VL', '2026-10-13', '2026-10-13', 'Personal family commitments.');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    app(LeaveManagement::class)->decide($leave->id, 'approved');
    Holiday::create(['name' => 'Configured holiday', 'holiday_date' => '2026-10-14', 'timezone' => 'Asia/Manila']);
    $this->travelTo(CarbonImmutable::parse('2026-10-18 10:00:00', 'UTC'));
    $summary = app(AttendanceAnalytics::class)->summarize(analyticsFilters(['to' => '2026-10-18']));
    expect($summary['counts'])->toMatchArray(['VL' => 1, 'Holiday' => 1, 'Rest day' => 2, 'Absent' => 2])
        ->and($summary['attendanceRate'])->toBe(0.0)->and($summary['onTimeRate'])->toBeNull();
});

it('paginates workdays and supports a full leap year without loading an unbounded detail ledger', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    analyticsEmployee();
    analyticsEmployee();
    $filters = analyticsFilters(['from' => '2028-01-01', 'to' => '2028-12-31']);
    $summary = app(AttendanceAnalytics::class)->summarize($filters, 2);
    expect($summary['total'])->toBe(732)->and($summary['details']->count())->toBe(50);
    $pdfSummary = app(AttendanceAnalytics::class)->summarize($filters, includeDetails: true);
    expect($pdfSummary['details']->count())->toBe(500)->and($pdfSummary['detailsTruncated'])->toBeTrue()->and($pdfSummary['total'])->toBe(732);
});

it('validates oversized ranges invalid grouping statuses and nonemployee IDs', function ($override) {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    if ($override === 'admin-id') {
        $override = ['employeeId' => (string) $admin->id];
    }
    expect(fn () => app(AttendanceAnalytics::class)->summarize(analyticsFilters($override)))->toThrow(ValidationException::class);
})->with([
    [['to' => '2027-10-14']], [['from' => 'invalid']], [['grouping' => 'hourly']], [['status' => 'Invented']], ['admin-id'],
]);

it('rejects employees inactive admins and pending admins from analytics and PDF generation', function ($attributes) {
    $actor = User::factory()->create($attributes);
    $this->actingAs($actor);
    Livewire::test('admin.reports')->assertForbidden();
    $exception = $actor->requires_password_setup ? HttpException::class : AuthorizationException::class;
    expect(fn () => app(AttendanceAnalytics::class)->summarize(analyticsFilters()))->toThrow($exception);
    expect(fn () => app(AttendancePdf::class)->generate(analyticsFilters()))->toThrow($exception);
    $this->get(route('admin.reports'))->assertForbidden();
})->with([[['role' => 'employee']], [['role' => 'admin', 'is_active' => false]], [['role' => 'admin', 'requires_password_setup' => true]]]);

it('denies exports after admin access is revoked on an already opened report', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    $component = Livewire::test('admin.reports');
    User::whereKey($admin->id)->update(['is_active' => false]);
    $component->call('exportCsv')->assertForbidden();
});

it('protects CSV formula prefixes and returns private downloads', function () {
    $employee = analyticsEmployee('  =FORMULA()', '=FORMULA()');
    $employee->employeeProfile()->update(['employee_id' => '+FORMULA()']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $component = new WorkdaySummary;
    $component->from = $component->to = '2026-10-13';
    $response = $component->exportCsv();
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();
    expect($csv)->toContain("'=FORMULA()", "'+FORMULA()", "'  =FORMULA()")
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
});

it('exports actual CSV and PDF files with identical employee filters', function () {
    $employee = analyticsEmployee('Finance');
    $this->actingAs($employee);
    app(AttendanceService::class)->clockIn();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    Livewire::test('admin.reports')->set('department', 'Finance')->set('employeeId', (string) $employee->id)
        ->call('exportCsv')->assertFileDownloaded('attendify-workdays-2026-10-13-2026-10-13.csv')
        ->call('exportPdf')->assertFileDownloaded('attendify-attendance-2026-10-13-2026-10-13.pdf');
    $contents = app(AttendancePdf::class)->generate(analyticsFilters(['employeeId' => (string) $employee->id]));
    expect(substr($contents, 0, 5))->toBe('%PDF-')->and(strlen($contents))->toBeGreaterThan(1000);
});

it('preserves overnight work-date grouping and original schedule timezone', function () {
    $employee = analyticsEmployee();
    $employee->employeeProfile->schedule->update(['start_time' => '22:00:00', 'end_time' => '06:00:00']);
    $this->travelTo(CarbonImmutable::parse('2026-10-13 14:00:00', 'UTC'));
    $this->actingAs($employee);
    app(AttendanceService::class)->clockIn();
    $this->travelTo(CarbonImmutable::parse('2026-10-13 22:00:00', 'UTC'));
    app(AttendanceService::class)->clockOut();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $summary = app(AttendanceAnalytics::class)->summarize(analyticsFilters());
    expect($summary['finalizedSeconds'])->toBe(28800)->and($summary['details']->sole()['date'])->toBe('2026-10-13')
        ->and($summary['periods']['2026-10-13']['Present'])->toBe(1);
});

it('uses bounded eager queries instead of issuing queries for each workday', function () {
    for ($i = 0; $i < 10; $i++) {
        analyticsEmployee();
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    DB::enableQueryLog();
    DB::flushQueryLog();
    app(AttendanceAnalytics::class)->summarize(analyticsFilters(['to' => '2026-11-30']));
    expect(count(DB::getQueryLog()))->toBeLessThan(30);
    DB::disableQueryLog();
});
