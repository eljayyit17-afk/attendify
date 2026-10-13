<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Attendify attendance report</title>
<style>
    @page { margin: 32pt 38pt 42pt; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #27272a; line-height: 1.5; }
    h1 { font-size: 20pt; font-weight: bold; margin: 0 0 4pt; }
    h2 { font-size: 11pt; margin: 18pt 0 8pt; }
    .brand { color: #6d4bb8; font-size: 10pt; font-weight: bold; margin-bottom: 12pt; }
    .muted { color: #71717a; } .meta { margin: 8pt 0 16pt; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th { text-align: left; background: #f4f4f5; font-weight: bold; }
    td, th { padding: 6pt; border-bottom: 0.5pt solid #e4e4e7; word-wrap: break-word; vertical-align: top; }
    thead { display: table-header-group; } tr { page-break-inside: avoid; }
    .metrics td { background: #fafafa; } .number { font-size: 16pt; font-weight: bold; }
    .section { page-break-before: always; } .note { font-size: 7pt; margin-top: 10pt; color: #71717a; }
</style></head><body>
<div class="brand">Attendify</div>
<h1>{{ $employee ? $employee->name.' — attendance report' : 'Workforce attendance report' }}</h1>
<p class="muted">{{ $filters['from'] }} to {{ $filters['to'] }} · {{ ucfirst($filters['grouping']) }} summaries</p>
<div class="meta">Department: {{ $filters['department'] === '__unassigned__' ? 'Unassigned' : ($filters['department'] ?: 'All current departments') }}<br>Status: {{ $filters['status'] ?: 'All statuses' }}<br>Generated {{ $generatedAt->setTimezone('Asia/Manila')->format('M j, Y H:i:s') }} Asia/Manila</div>
<table class="metrics"><tr>@foreach (['Present','Late','Absent','VL','SL'] as $state)<td><span class="muted">{{ $state }}</span><br><span class="number">{{ number_format($report['counts'][$state] ?? 0) }}</span></td>@endforeach</tr></table>
<p>{{ number_format($report['employeeCount']) }} employees represented · {{ number_format($report['total']) }} matching employee-workdays · {{ number_format($report['finalizedSeconds'] / 3600, 2) }} finalized elapsed hours · {{ number_format($report['incomplete']) }} incomplete sessions</p>
<p>Recorded attendance rate: {{ $report['attendanceRate'] === null ? 'Not available' : $report['attendanceRate'].'%' }} · On-time rate: {{ $report['onTimeRate'] === null ? 'Not available' : $report['onTimeRate'].'%' }}</p>
<h2>Attendance trends</h2>
<table><thead><tr><th>Period start</th>@foreach (['Present','Late','Absent','VL','SL'] as $state)<th>{{ $state }}</th>@endforeach<th>Other states</th></tr></thead><tbody>
@forelse ($report['periods'] as $period => $counts)<tr><td>{{ $period }}</td>@foreach (['Present','Late','Absent','VL','SL'] as $state)<td>{{ $counts[$state] ?? 0 }}</td>@endforeach<td>{{ array_sum(array_diff_key($counts, array_flip(['Present','Late','Absent','VL','SL']))) }}</td></tr>@empty<tr><td colspan="7">No matching workdays.</td></tr>@endforelse
</tbody></table>
<h2>Current department comparisons</h2>
<table><thead><tr><th style="width:30%">Department</th>@foreach (['Present','Late','Absent','VL','SL'] as $state)<th>{{ $state }}</th>@endforeach<th>Attendance rate</th></tr></thead><tbody>
@forelse ($report['departmentCounts'] as $department => $counts)<tr><td>{{ $department }}</td>@foreach (['Present','Late','Absent','VL','SL'] as $state)<td>{{ $counts[$state] ?? 0 }}</td>@endforeach<td>{{ app(\App\Services\AttendanceAnalytics::class)->attendanceRate($counts) === null ? 'Not available' : app(\App\Services\AttendanceAnalytics::class)->attendanceRate($counts).'%' }}</td></tr>@empty<tr><td colspan="7">No department data for these filters.</td></tr>@endforelse
</tbody></table>
<div class="section"><h2>Employee-workday details</h2>@if ($report['detailsTruncated'])<p class="muted">Showing the first 500 of {{ number_format($report['total']) }} matching workdays. All summaries above cover the complete filtered result. Download CSV for the complete workday ledger.</p>@endif<table><thead><tr><th style="width:22%">Employee / ID</th><th style="width:18%">Current department</th><th style="width:10%">Work date</th><th style="width:11%;white-space:nowrap">Status</th><th style="width:12%">Clock in · Manila</th><th style="width:12%">Clock out · Manila</th><th style="width:15%">Working time</th></tr></thead><tbody>
@forelse ($report['details'] as $row)<tr><td>{{ $row['employee'] }}<br><span class="muted">{{ $row['employee_code'] ?? 'Not provided' }}</span></td><td>{{ $row['department'] ?: 'Unassigned' }}</td><td>{{ $row['date'] }}<br><span class="muted">{{ $row['timezone'] }}</span></td><td>{{ $row['status'] }}</td><td>{{ $row['session']?->clock_in_at->setTimezone('Asia/Manila')->format('M j, Y H:i:s') ?? '—' }}</td><td>{{ $row['session']?->clock_out_at?->setTimezone('Asia/Manila')->format('M j, Y H:i:s') ?? '—' }}</td><td>{{ $row['session']?->durationLabel() ?? 'Not applicable' }}</td></tr>@empty<tr><td colspan="7">No matching workdays for the selected filters.</td></tr>@endforelse
</tbody></table></div>
<p class="note">Work dates and shift ends follow the recorded schedule timezone; clock times use Asia/Manila. Weekly periods start Monday. Periods are restricted to the selected range. Absence is calculated only after shift end. Approved leave, holidays, rest days, and untracked dates are excluded from the attendance-rate denominator. Present is on time; Late is after the original shift start with no assumed grace period. Attendance rate = (Present + Late) / (Present + Late + Absent); on-time rate = Present / (Present + Late). Empty denominators are unavailable. Department membership uses current profiles. Elapsed hours are not payroll-approved paid hours or leave balances.</p>
</body></html>
