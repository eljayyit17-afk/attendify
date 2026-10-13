# Leave and absence management

## Screens

- Employees: **My leave** (`/employee/leave`) provides VL/SL requests, decision history, and cancellation. **My calendar** (`/employee/calendar`) displays attendance, approved leave, holidays, rest days, and pending requests separately. The employee dashboard displays approved leave and holiday status and disables clock-in for those days.
- Administrators: **Leave approvals** (`/admin/leave`) provides pending reviews and status filters, with optional decision remarks. **Holidays** (`/admin/holidays`) configures non-working dates for a specific schedule timezone.
- **Reports** (`/admin/reports`) and **Attendance → Filter workday statuses** (`/admin/workdays`) provide employee/date/status filtering, pagination, actual workday totals, clock times, finalized duration, and CSV download. The admin dashboard displays pending approvals and current-work-date totals.

## Explicit policies

Requests are full calendar-date intervals, inclusive of both endpoints, with a maximum of 90 days. Dates must be within one year before/after today's Asia/Manila calendar date. Partial-day inputs are rejected. Dates identify shift-start work dates in the employee's schedule timezone; an overnight shift belongs to its starting date. No paid status, accrual, entitlement, or balance is inferred.

Pending and approved requests reserve every date in their range, including rest days, so VL and SL cannot overlap even when concurrent requests use different leave types. A user-row lock serializes mutations; unique database reservations enforce employee/date uniqueness. Rejection and cancellation release reservations but retain requests and audit history. A rejected/cancelled interval may be requested again. Different employees may request the same dates.

Only active employees with completed password setup and an employee profile can submit requests. Employees cannot choose another identity, inspect another employee's requests/calendar, or decide requests. Only active administrators can approve/reject a pending request. Decisions record the administrator, UTC server timestamp, and optional remarks. Already-decided or cancelled requests cannot be decided again. Full-day approval is blocked if any recorded attendance exists in the interval; attendance is never silently erased or disguised as leave.

Retroactive requests and approvals are supported within the request date limits. Approved VL/SL replaces a derived absence for covered scheduled, non-holiday workdays. Pending leave never excuses an absence. Employees may cancel pending requests; approved cancellation is allowed only before the first date's scheduled shift starts (or the start date's Manila midnight if no shift is tracked for that date). Approved leave already underway cannot be cancelled through the employee UI. Administrative reversal of an existing decision is not implemented.

Holiday dates apply to scheduled shifts whose timezone matches the configured holiday timezone. Holidays are explicitly entered; no country's official holiday calendar is inferred. Rest days remain rest days and are never charged as leave. Holiday creation/removal can intentionally reclassify earlier derived reports; removal requires UI confirmation. Attendance already recorded on a date remains visible as Present/Late. Clock-in is blocked for approved full-day leave and matching holidays; existing open attendance may still be clocked out.

Leave reasons and remarks are capped at 500 characters. The UI asks for a general administrative reason and discourages diagnoses and medical records. There are no medical attachments, detailed medical fields, or medical information in attendance CSV exports. Reasons are visible only in the employee's own history and authorized admin review screens.

## Reliable absence and schedule history

Schedule-assignment snapshots are separate from attendance and leave. They capture shift name, start/end, timezone, weekdays, and UTC effective interval. A snapshot begins on a valid assignment to an active, onboarded employee. Schedule edits, assignment changes, deactivation, and reactivation are captured through model events. A shift belongs to the snapshot effective at its planned start; edits do not rewrite earlier shifts. Existing attendance retains its own original shift snapshot as before.

The migration initializes snapshot tracking from its execution time for existing configured assignments. Earlier missing attendance is **not** inferred from current schedules. Such dates show Unscheduled; actual earlier attendance still shows Present/Late. A shift already underway when reliable tracking starts is not used to infer absence. This conservative boundary avoids manufacturing historical absences. Direct SQL/bulk updates bypass model events and must not be used to change schedule assignments or account eligibility.

Reports derive workdays on read; they do not create synthetic clock-ins, clock-outs, or absence sessions. A scheduled day becomes Absent only when its timezone-aware planned end is at/before the server's current UTC time and no attendance, approved leave, or matching holiday covers it. Rest days, unconfigured/unassigned dates, dates before the tracking interval, and days before the known hire date are never marked absent. Overnight and daylight-saving boundaries use UTC shift endpoints.

Present and Late are disjoint: Present means clock-in at/before the attendance snapshot's shift start; Late means after it, with no implicit grace period. Incomplete sessions still prove attendance but have no finalized duration. Approved VL/SL is displayed only for scheduled workdays, not holidays/rest days. Report totals count employee-workdays, not unique people or paid hours. Dashboard totals use the displayed work-date label; each employee's shift endpoints retain their own timezone.

## Audit, setup, and validation

Leave decisions/cancellations append an immutable record containing actor, previous/new status, remarks, and UTC time in the same transaction as the request change. Model guards and database triggers prevent changing/deleting audit entries. Existing manual attendance corrections retain their version checks and before/after audit entries. Referenced accounts are retained; deactivate them instead of deleting records.

Run `php artisan migrate`, configure schedules and assignments, configure applicable holidays, and build assets with `npm run build`. No production leave, holidays, attendance, or absence examples are seeded. Absences are calculated on read, so no cron task is required to produce them. Reports use bounded employee batches and hold only the current 50-row page; admin analytics now supports up to 366 days. See reports-analytics.md for grouping, department comparisons, and PDF export limits. CSV exports stream matching records and protect spreadsheet-formula prefixes in employee names.

Run `php artisan test` for leave/absence Pest tests and the complete existing suite. `php scripts/verify-attendance-mysql.php` verifies MySQL constraints in connection-local temporary tables and confirms both attendance and leave audit triggers. Application data tables are not populated by that verification.

## Remaining boundaries

Partial-day leave, paid-leave configuration/balances, accruals, leave notifications, approval delegation, administrative decision reversal, payroll integration, and imported regional holiday calendars are not implemented. Existing manual corrections edit attendance sessions; entirely missing clock sessions cannot be fabricated through that flow. CSV reports are operational workday reports, not payroll statements.
