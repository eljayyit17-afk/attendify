<?php

use App\Models\EmployeeProfile;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\EmployeeInvitation;
use App\Services\EmployeeManagement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function employeeFormData(array $overrides = []): array
{
    return array_replace([
        'name' => 'Morgan Rivera', 'email' => 'morgan@example.com', 'employeeCode' => 'EMP-101',
        'department' => 'Operations', 'position' => 'Team lead', 'hireDate' => '2026-10-01', 'scheduleId' => '',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    Notification::fake();
    config(['mail.default' => 'smtp']);
});

it('creates an employee and profile atomically with an unknowable initial password', function () {
    $component = Livewire::test('admin.employees')->call('createEmployee');
    foreach (employeeFormData(['email' => '  MORGAN@example.com  ']) as $field => $value) {
        $component->set('form.'.$field, $value);
    }
    $component->call('saveEmployee')->assertHasNoErrors()->assertSet('showEditor', false);
    $employee = User::where('email', 'morgan@example.com')->firstOrFail();
    expect($employee->role)->toBe('employee')->and($employee->is_active)->toBeTrue()
        ->and($employee->requires_password_setup)->toBeTrue()
        ->and(Hash::needsRehash($employee->password))->toBeFalse()
        ->and(Hash::check('password', $employee->password))->toBeFalse()
        ->and($employee->employeeProfile->employee_id)->toBe('EMP-101')
        ->and($employee->employeeProfile->department)->toBe('Operations');
    $component->assertDontSee($employee->password);
    Notification::assertSentTo($employee, EmployeeInvitation::class, function ($notification) use ($employee, $component) {
        $component->assertDontSee($notification->token);

        return $notification instanceof ShouldBeEncrypted && Password::tokenExists($employee, $notification->token);
    });
});

it('generates unique employee IDs when none are supplied', function () {
    $service = app(EmployeeManagement::class);
    $one = $service->save(null, employeeFormData(['employeeCode' => '']));
    $two = $service->save(null, employeeFormData(['email' => 'second@example.com', 'employeeCode' => '']));
    expect($one->employeeProfile->employee_id)->toStartWith('EMP-')
        ->not->toBe($two->employeeProfile->employee_id);
});

it('validates profile and account fields without creating partial records', function ($field, $value) {
    $component = Livewire::test('admin.employees')->call('createEmployee');
    foreach (employeeFormData([$field => $value]) as $key => $entry) {
        $component->set('form.'.$key, $entry);
    }
    $component->call('saveEmployee')->assertHasErrors(['form.'.$field]);
    expect(User::where('role', 'employee')->count())->toBe(0)->and(EmployeeProfile::count())->toBe(0);
})->with([
    ['name', '   '], ['email', 'invalid'], ['department', ''], ['position', ''],
    ['hireDate', '2026-02-30'], ['scheduleId', '999'], ['employeeCode', 'ID WITH SPACES'],
]);

it('rejects duplicate emails and employee IDs', function () {
    app(EmployeeManagement::class)->save(null, employeeFormData());
    $component = Livewire::test('admin.employees')->call('createEmployee');
    foreach (employeeFormData() as $key => $value) {
        $component->set('form.'.$key, $value);
    }
    $component->call('saveEmployee')->assertHasErrors(['form.email', 'form.employeeCode']);
    expect(User::where('role', 'employee')->count())->toBe(1);
});

it('edits profiles and assigns or removes schedules without resetting existing passwords', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $password = $employee->password;
    $schedule = Schedule::create(['name' => 'Day shift']);
    $component = Livewire::test('admin.employees')->call('editEmployee', $employee->id)
        ->set('form.department', 'Customer support')->set('form.scheduleId', (string) $schedule->id)
        ->call('saveEmployee')->assertHasNoErrors();
    expect($employee->refresh()->employeeProfile->schedule_id)->toBe($schedule->id)
        ->and($employee->employeeProfile->department)->toBe('Customer support')
        ->and($employee->password)->toBe($password);
    $component->call('editEmployee', $employee->id)->set('form.scheduleId', '')->call('saveEmployee')->assertHasNoErrors();
    expect($employee->refresh()->employeeProfile->schedule_id)->toBeNull();
});

it('requires new password setup and invalidates old links when the email changes', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $employee->forceFill(['requires_password_setup' => false, 'email_verified_at' => now()])->save();
    $oldToken = Password::createToken($employee);
    $oldPassword = $employee->password;
    Livewire::test('admin.employees')->call('editEmployee', $employee->id)
        ->set('form.email', 'new@example.com')->call('saveEmployee')->assertHasNoErrors();
    expect($employee->refresh()->email)->toBe('new@example.com')
        ->and($employee->requires_password_setup)->toBeTrue()
        ->and($employee->email_verified_at)->toBeNull()->and($employee->password)->not->toBe($oldPassword);
    expect(DB::table('password_reset_tokens')->where('email', 'morgan@example.com')->exists())->toBeFalse();
    expect(Password::tokenExists($employee, $oldToken))->toBeFalse();
    Notification::assertSentTo($employee, EmployeeInvitation::class);
});

it('deactivates and reactivates employees while retaining profiles and revoking sessions', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $token = Password::createToken($employee);
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'employee-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => time()]);
    Livewire::test('admin.employees')->call('confirmStatus', $employee->id)
        ->assertSet('targetActive', false)->call('changeStatus')->assertHasNoErrors();
    expect($employee->refresh()->is_active)->toBeFalse()
        ->and($employee->employeeProfile)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'employee-session')->exists())->toBeFalse()
        ->and(Password::tokenExists($employee, $token))->toBeFalse();
    Livewire::test('admin.employees')->call('confirmStatus', $employee->id)->call('changeStatus');
    expect($employee->refresh()->is_active)->toBeTrue();
});

it('filters and searches employee profiles on the server', function () {
    $service = app(EmployeeManagement::class);
    $service->save(null, employeeFormData());
    $second = $service->save(null, employeeFormData(['name' => 'Casey Santos', 'email' => 'casey@example.com', 'employeeCode' => 'EMP-202', 'department' => 'Finance']));
    $service->setActive($second, false);
    Livewire::test('admin.employees')->set('search', 'Finance')->assertSee('Casey Santos')->assertDontSee('Morgan Rivera')
        ->set('status', 'active')->assertViewHas('employees', fn ($employees) => $employees->total() === 0)
        ->set('search', 'EMP-101')->assertSee('Morgan Rivera')->assertDontSee('Casey Santos');
});

it('rejects employee and inactive admin access to the component and its actions', function ($attributes) {
    $actor = User::factory()->create($attributes);
    $this->actingAs($actor);
    $this->get(route('admin.employees'))->assertForbidden();
    Livewire::test('admin.employees')->assertForbidden();
    expect(fn () => app(EmployeeManagement::class)->save(null, employeeFormData()))
        ->toThrow(AuthorizationException::class);
})->with([[['role' => 'employee']], [['role' => 'admin', 'is_active' => false]]]);

it('does not allow an admin to edit or deactivate another admin through employee actions', function () {
    $other = User::factory()->create(['role' => 'admin']);
    Livewire::test('admin.employees')->call('editEmployee', $other->id)->assertForbidden();
    Livewire::test('admin.employees')->call('confirmStatus', $other->id)->assertForbidden();
});

it('locks employee IDs against client tampering', function () {
    expect(fn () => Livewire::test('admin.employees')->set('editingId', $this->admin->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('saves accounts without logging onboarding secrets when delivery is not configured', function () {
    config(['mail.default' => 'log']);
    $component = Livewire::test('admin.employees')->call('createEmployee');
    foreach (employeeFormData() as $field => $value) {
        $component->set('form.'.$field, $value);
    }
    $component->call('saveEmployee')->assertHasNoErrors()->assertSee('Configure a delivery mailer');
    expect(User::where('email', 'morgan@example.com')->exists())->toBeTrue()
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
    Notification::assertNothingSent();
});

it('throttles repeated invitations and rejects invitations to inactive accounts', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    Livewire::test('admin.employees')->call('resendInvitation', $employee->id)
        ->call('resendInvitation', $employee->id)->assertSee('Wait one minute');
    app(EmployeeManagement::class)->setActive($employee, false);
    Livewire::test('admin.employees')->call('resendInvitation', $employee->id)
        ->assertSee('Only active accounts awaiting password setup');
});

it('completes onboarding through Fortify and rejects token reuse', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $token = Password::createToken($employee);
    auth()->logout();
    $payload = ['email' => $employee->email, 'token' => $token, 'password' => 'A-strong-password-42!', 'password_confirmation' => 'A-strong-password-42!'];
    $this->post(route('password.update'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('login', absolute: false));
    expect($employee->refresh()->requires_password_setup)->toBeFalse()
        ->and($employee->email_verified_at)->not->toBeNull()
        ->and(Hash::check($payload['password'], $employee->password))->toBeTrue();
    $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $employee->email, 'password' => $payload['password']])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($employee);
});

it('rejects expired setup links', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $token = Password::createToken($employee);
    $this->travel(61)->minutes();
    auth()->logout();
    $this->post(route('password.update'), ['email' => $employee->email, 'token' => $token, 'password' => 'A-strong-password-42!', 'password_confirmation' => 'A-strong-password-42!'])
        ->assertSessionHasErrors('email');
    expect($employee->refresh()->requires_password_setup)->toBeTrue();
});

it('blocks login and existing authenticated access for inactive or pending employees', function ($attributes) {
    $employee = User::factory()->create($attributes);
    auth()->logout();
    $this->post(route('login.store'), ['email' => $employee->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->actingAs($employee)->get(route('profile.edit'))->assertForbidden();
})->with([[['is_active' => false]], [['requires_password_setup' => true]]]);

it('keeps public registration disabled', function () {
    auth()->logout();
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'Uninvited', 'email' => 'uninvited@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
});

it('encrypts onboarding tokens in serialized queue payloads', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $token = Password::createToken($employee);
    $notification = new EmployeeInvitation($token);
    $job = new SendQueuedNotifications($employee, $notification, ['mail']);
    $job->beforeCommit();
    $jobId = app('queue')->connection('database')->push($job, '', 'default');
    $payload = DB::table('jobs')->where('id', $jobId)->value('payload');
    expect($payload)->not->toContain($token)->not->toContain($employee->email);
    $command = json_decode($payload, true)['data']['command'];
    expect(Crypt::decryptString($command))->toContain($token);
});

it('discards queued invitations after deactivation or expiration', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $notification = new EmployeeInvitation(Password::createToken($employee));
    expect($notification->shouldSend($employee, 'mail'))->toBeTrue();
    $this->travel(61)->minutes();
    expect($notification->shouldSend($employee, 'mail'))->toBeFalse();
    $this->travelBack();
    app(EmployeeManagement::class)->setActive($employee, false);
    expect($notification->shouldSend($employee->refresh(), 'mail'))->toBeFalse();
});

it('rolls back account creation when profile persistence fails', function () {
    $service = app(EmployeeManagement::class);
    $service->save(null, employeeFormData());
    expect(fn () => $service->save(null, employeeFormData(['email' => 'duplicate-profile@example.com'])))
        ->toThrow(UniqueConstraintViolationException::class);
    expect(User::where('email', 'duplicate-profile@example.com')->exists())->toBeFalse()
        ->and(EmployeeProfile::count())->toBe(1);
});

it('backfills existing employees without inventing employment details', function () {
    $migration = require database_path('migrations/2026_10_13_130000_create_employee_profiles_and_schedules.php');
    $migration->down();
    $legacy = User::factory()->create(['role' => 'employee']);
    $migration->up();
    $profile = $legacy->refresh()->employeeProfile;
    expect($profile->employee_id)->toStartWith('EMP-')
        ->and($profile->department)->toBeNull()->and($profile->position)->toBeNull()
        ->and($profile->hire_date)->toBeNull()->and($legacy->requires_password_setup)->toBeFalse();
    expect($this->admin->refresh()->employeeProfile)->toBeNull();
});

it('rejects password setup for deactivated accounts', function () {
    $employee = app(EmployeeManagement::class)->save(null, employeeFormData());
    $token = Password::createToken($employee);
    $employee->forceFill(['is_active' => false])->save();
    auth()->logout();
    $this->post(route('password.update'), ['email' => $employee->email, 'token' => $token, 'password' => 'A-strong-password-42!', 'password_confirmation' => 'A-strong-password-42!'])
        ->assertSessionHasErrors('email');
    expect($employee->refresh()->requires_password_setup)->toBeTrue();
});

it('allows an inactive employee with an existing session to log out', function () {
    $employee = User::factory()->create(['is_active' => false]);
    $this->actingAs($employee)->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});
