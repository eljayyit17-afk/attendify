<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\EmployeeInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeManagement
{
    /** @param array<string, mixed> $data */
    public function save(?User $employee, array $data): User
    {
        Gate::authorize($employee ? 'update' : 'create', $employee ?? User::class);

        return DB::transaction(function () use ($employee, $data) {
            if ($employee) {
                $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
                Gate::authorize('update', $employee);
                if ($employee->email !== $data['email']) {
                    $this->revokeAccess($employee);
                    $employee->forceFill([
                        'email_verified_at' => null,
                        'requires_password_setup' => true,
                        'password' => Str::random(64),
                    ]);
                }
            } else {
                $employee = new User;
                $employee->forceFill([
                    'role' => 'employee',
                    'is_active' => true,
                    'requires_password_setup' => true,
                    // An unknowable initial credential; only its hash is persisted.
                    'password' => Str::random(64),
                ]);
            }

            $employee->fill(['name' => $data['name'], 'email' => $data['email']])->save();
            $employee->employeeProfile()->updateOrCreate([], [
                'employee_id' => $data['employeeCode'] ?: 'EMP-'.Str::ulid(),
                'department' => $data['department'],
                'position' => $data['position'],
                'hire_date' => $data['hireDate'],
                'schedule_id' => $data['scheduleId'] ?: null,
            ]);

            return $employee->refresh();
        });
    }

    public function setActive(User $employee, bool $active): void
    {
        Gate::authorize('update', $employee);
        DB::transaction(function () use ($employee, $active) {
            $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
            Gate::authorize('update', $employee);
            if (! $active) {
                $this->revokeAccess($employee);
            }
            $employee->forceFill(['is_active' => $active])->save();
        });
    }

    public function invite(User $employee): void
    {
        Gate::authorize('update', $employee);
        if (! $this->canDeliverMail((string) config('mail.default'))) {
            throw ValidationException::withMessages(['invitation' => 'Configure a delivery mailer before sending invitations. Log and array mailers cannot deliver onboarding links.']);
        }

        DB::transaction(function () use ($employee) {
            $employee = User::query()->lockForUpdate()->findOrFail($employee->id);
            Gate::authorize('update', $employee);
            if (! $employee->is_active || ! $employee->requires_password_setup) {
                throw ValidationException::withMessages(['invitation' => 'Only active accounts awaiting password setup can be invited.']);
            }
            $result = Password::broker()->sendResetLink(['email' => $employee->email], function (User $user, #[\SensitiveParameter] string $token) {
                $user->notify(new EmployeeInvitation($token));
            });
            if ($result !== Password::RESET_LINK_SENT) {
                throw ValidationException::withMessages(['invitation' => $result === Password::RESET_THROTTLED
                    ? 'An invitation was requested recently. Wait one minute before trying again.'
                    : 'The invitation could not be queued. Please try again.']);
            }
        });
    }

    private function revokeAccess(User $employee): void
    {
        Password::broker()->deleteToken($employee);
        $employee->forceFill(['remember_token' => null]);
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table((string) config('session.table', 'sessions'))
                ->where('user_id', $employee->id)->delete();
        }
    }

    /** @param list<string> $visited */
    private function canDeliverMail(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return false;
        }
        $config = config('mail.mailers.'.$mailer, []);
        if (in_array($config['transport'] ?? '', ['failover', 'roundrobin'], true)) {
            $children = $config['mailers'] ?? [];
            if (! is_array($children) || $children === []) {
                return false;
            }
            foreach ($children as $child) {
                if (! is_string($child) || ! $this->canDeliverMail($child, [...$visited, $mailer])) {
                    return false;
                }
            }

            return true;
        }

        return in_array($config['transport'] ?? '', ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true);
    }
}
