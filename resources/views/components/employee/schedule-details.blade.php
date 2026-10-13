@props(['schedule'])
@if ($schedule && $schedule->isConfigured())
    <dl class="grid gap-6 p-6 sm:grid-cols-2">
        <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Assigned schedule</dt><dd class="mt-2 text-sm font-medium break-words">{{ $schedule->name }}</dd></div>
        <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Shift hours (schedule timezone)</dt><dd class="mt-2 text-sm font-medium tabular-nums">{{ substr($schedule->start_time, 0, 5) }} – {{ substr($schedule->end_time, 0, 5) }}{{ substr($schedule->end_time, 0, 5) < substr($schedule->start_time, 0, 5) ? ' (next day)' : '' }}</dd></div>
        <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Working days</dt><dd class="mt-2 text-sm">{{ $schedule->daysLabel() }}</dd></div>
        <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Schedule timezone</dt><dd class="mt-2 text-sm">{{ $schedule->timezone }}</dd></div>
    </dl>
@else
    <x-admin.empty-state icon="calendar-days" :title="$schedule ? 'Schedule needs configuration' : 'No schedule assigned'" description="Your administrator needs to assign a schedule with shift hours and working days before you can clock in." />
@endif
