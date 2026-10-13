# Employee portal and attendance policy

## Implemented routes

- `/employee/dashboard`: current attendance, clock-in/out, assigned shift, and the latest five personal records.
- `/employee/attendance`: personal history, 15 records per page, inclusive work-date filters stored in the URL.
- `/employee/profile`: read-only employee details from the existing profile.
- `/employee/schedule`: assigned shift and working-day rules.
- `/admin/schedules`: create/edit named shifts with an IANA timezone, daily start/end times, and ISO working weekdays (Monday = 1).
- `/admin/attendance`: paginated attendance register, corrections to existing records, and the latest ten audit entries per record.

The existing role-based `/dashboard` redirect sends employees to their portal. Fortify and public-registration settings are preserved. Only active employees who have completed password setup can clock attendance or view the portal. Every Livewire request checks authorization. Every attendance mutation additionally reloads and locks the current user from the database. Employee clock methods accept no identity or time arguments, and personal queries always use the authenticated user's relationship.

## Time and shifts

All attendance instants are generated on the server and stored as UTC `DATETIME` values with second precision. The custom UTC cast also reads them as UTC regardless of the application timezone. Attendance clock times and correction inputs are displayed in **Asia/Manila**. The employee schedule displays its native timezone and hours explicitly.

A work date is the local calendar date on which the assigned shift starts, using its IANA timezone. Overnight shifts end on the next local date. After-midnight clock-ins before the overnight end belong to the preceding shift-start date. A shift beginning at midnight allows early clock-in on the preceding calendar date but still belongs to the upcoming shift-start date.

Shifts must span more than zero and no more than 16 wall-clock hours. Clock-in opens exactly one real hour before the scheduled start and closes at the scheduled end (end is exclusive). Working days refer to the shift-start date, so the morning of an overnight shift can fall on a rest day. Dates outside the working-day list do not create absences or records. No overtime/rest-day clock-ins are allowed without a schedule change. Missing profiles, missing schedule assignments, and legacy name-only schedules block clock-in with a clear explanation.

Clock-out closes the user's active session even if it is past midnight, past the shift end, or their schedule has changed. The stored work date, schedule name, timezone, and UTC planned bounds remain unchanged. Duration is the exact UTC second difference between clock-in and clock-out; daylight-saving changes are reflected in elapsed time. This is continuous elapsed time: breaks are not automatically deducted and it is not payroll-approved paid time.

## Incomplete records and corrections

One continuous session is supported per employee per work date. A generated `active_user_id` unique index permits only one open session across all dates; a second unique index prevents duplicate employee/work-date records. User-row locks serialize employee mutations, employee-account updates, and manual corrections. Clock-out without an open session and repeated submissions are rejected. The database additionally prevents clock-out preceding clock-in.

An open session up to 24 hours old can be clocked out. After 24 hours it remains incomplete and blocks further clock-ins until an administrator records a correction. No clock-out is fabricated and no finalized duration is shown for incomplete records. Active-session elapsed time is labeled provisional on the dashboard and refreshed every minute while visible.

Only active administrators can correct an existing record. Corrections require a reason of 10–1000 characters and the record's current version. Inputs are interpreted in Asia/Manila and converted to UTC. Clock-in must stay within the original shift's clock-in window; clock-out must be at or after clock-in, at or before the server's present time, and no more than 24 hours after clock-in. Old records require an actual clock-out. Reopening a recent record is allowed only if the employee has no other open session. No-op changes and stale versions are rejected.

Each correction commits the record change and an audit entry in the same transaction. Audit entries contain the actor, reason, server UTC time, and original/new clock times, work date, and version. Model guards and database triggers prevent audit updates/deletions. Foreign keys retain records if an account deletion is attempted; the account-deletion flow explains the restriction without logging the user out. Deactivate accounts rather than deleting attendance history. Direct database edits remain an operator responsibility and must not bypass the application correction service.

## Setup and verification

Run `php artisan migrate` and `npm run build`. Configure shifts in **Admin → Schedules**, then assign them through **Admin → Employees**. Legacy schedules keep their names but remain unconfigured until their rules are saved. No production attendance or schedule data is seeded.

Run `php artisan test` for Pest and existing tests. Attendance coverage includes permissions, identity isolation, duplicate writes and submissions, no-session clock-out, invalid schedules, rest days, local dates, midnight, overnight and daylight-saving shifts, filter pagination, UTC casts, incomplete records, correction validation/versioning, and audit retention. `php scripts/verify-attendance-mysql.php` checks MySQL's actual generated/unique/check constraints in a connection-local temporary table and confirms that audit-protection triggers are installed. It never writes to application data tables.

## Boundaries

Leave requests, personal work calendars, configurable holidays, absence calculations, and filtered CSV workday reports are now available; see [Leave and absence management](leave-absence-management.md). Break tracking, multiple shifts per work date, overtime approval, dated schedule exceptions, and payroll calculations remain unimplemented. Manual corrections edit existing sessions; creating entirely missing attendance days is not implemented. This is a live clocking interface, not an offline or device clock integration.
