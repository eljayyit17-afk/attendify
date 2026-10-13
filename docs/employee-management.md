# Employee management

The existing `/admin/employees` page supports employee creation, editing, profile search, account-status filters, pagination, and confirmed activation/deactivation. Administrative policies and Livewire request checks protect every action. Public registration remains disabled.

## Deployment

1. Install the locked Composer dependencies. Pest is a development dependency.
2. Run `php artisan migrate`. The migration adds employee profiles and schedule references, and backfills existing employee IDs. Existing passwords remain unchanged; unknown department, position, and hire date values remain empty.
3. Configure a delivery mailer such as SMTP, plus the correct HTTPS `APP_URL` and sender address. Log/array mailers, including fallbacks to them, cannot send employee invitations.
4. Run a queue worker, for example `php artisan queue:work --tries=3`. Use your existing queue deployment and process manager; the database queue tables already exist. Restart workers when deploying changes. Monitor failed jobs through the usual Laravel queue tooling.
5. Run `npm run build` and the test suite with PHP 8.4 or later, as required by the locked test dependencies.

## Onboarding and account changes

Employee creation saves an account with a random, hashed credential that is never displayed, and queues an encrypted invitation job. The employee follows an expiring, single-use Fortify password-reset link and selects their own password. Pending accounts cannot sign in. Password setup verifies ownership of the invited email address.

The UI reports whether an invitation was queued; this does not claim delivery. If delivery is not configured, the account is retained and the administrator can use **Send invitation** after configuration. The password broker throttles repeated requests. Expired links can be replaced through **Forgot your password?** or by an administrator.

Changing an employee's email revokes existing database sessions, remember tokens, and old password links, then requires password setup at the new address. Deactivation revokes sessions and links and blocks all authenticated web access, including already-open sessions using other session drivers. Reactivation retains the existing profile and credentials. Pending accounts still need an invitation and password setup. Invalid or expired queued invitations are discarded before delivery.

## Scheduling boundary

Profiles optionally reference a real record in `schedules`. Administrators can configure shift hours, IANA timezones, and working days in **Schedules**, then assign them from the employee directory. See [Employee portal and attendance policy](employee-portal.md) and [Leave and absence management](leave-absence-management.md) for clocking, reporting, schedule history, and audit rules. Payroll remains unimplemented. No schedule examples or employee records are seeded into production.

## Verification

Pest coverage includes role authorization, locked identifiers, field validation, unique constraints, transaction rollback, migration backfill, search/filtering, schedule assignment, credential handling, invitation throttling/encryption/expiry, account deactivation, and Fortify onboarding. Existing PHPUnit-style tests continue to run alongside Pest.
