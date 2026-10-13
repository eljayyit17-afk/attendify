# Attendance reports and analytics

`/admin/reports` and `/admin/workdays` share the Reports & Analytics module. Only active administrators who have completed onboarding can open reports or invoke exports. Authorization is checked again against the database for each action.

## Reporting scope

- Daily, Monday-based weekly, and monthly periods; partial weeks/months include only selected dates.
- Up to 366 inclusive work dates. Filters apply consistently to totals, trends, comparisons, the ledger, and exports.
- Employee, current department, attendance status, and work-date filters. Historical department memberships are not recorded; comparisons explicitly use current profiles.
- Present means arrival on or before the recorded scheduled start. Late means arrival after that start; there is no additional grace period.
- Attendance rate is `(Present + Late) / (Present + Late + Absent)`. On-time rate is `Present / (Present + Late)`. Zero denominators display unavailable, never an invented percentage.
- Approved VL/SL, holidays, rest days, not-due shifts, and unscheduled dates are excluded from attendance-rate denominators. Existing attendance records take precedence over leave. Automatic absence uses historical schedule assignments and only appears after a scheduled shift ends. No absence is inferred before attendance tracking began.
- Work dates follow schedule timezones and overnight-shift rules. Clock timestamps are displayed in Asia/Manila. Finalized elapsed hours sum closed sessions; open sessions are counted separately, not credited as completed hours. These are not payroll hours or paid-leave balances.

## Exports and implementation

CSV streams every matching row and protects employee names, employee codes, and departments against spreadsheet-formula prefixes. PDF uses Dompdf, A4 landscape pages, repeated table headings, and confidential page footers. All PDF summaries cover the entire filter set; the detailed PDF ledger is limited to 500 rows to bound synchronous rendering. Larger ledgers are available through CSV. Both exports are private downloads with no-store cache headers and contain no leave reasons or medical information.

`AttendanceAnalytics` aggregates the existing `WorkdayReport` stream in one pass. Employee relations are eager loaded in date-range-adjusted batches. The screen retains only its current 50-row page. No additional migration, materialized absence table, scheduler, or fake statistics were added.

Relevant verification lives in `tests/Feature/AttendanceAnalyticsTest.php`, with existing attendance and leave tests covering the shared business rules. Install PHP dependencies with `composer install`; no frontend dependency was added.
