<?php

use App\Livewire\Employee\PortalOverview;
use Livewire\Attributes\Title;

new #[Title('Employee overview')] class extends PortalOverview {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8" wire:poll.60s.visible>
    <x-admin.page-header title="Your workday" description="Record your attendance and review your assigned work schedule.">
        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $now->setTimezone('Asia/Manila')->format('l, M j, Y') }} · Asia/Manila</span>
    </x-admin.page-header>
    <div class="flex flex-wrap gap-3"><flux:button :href="route('employee.leave')" variant="outline" wire:navigate>My leave requests</flux:button><flux:button :href="route('employee.calendar')" variant="ghost" wire:navigate>Work calendar</flux:button></div>
    @if ($notice)<p role="status" class="rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">{{ $notice }}</p>@endif
    @error('attendance')<p role="alert" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">{{ $message }}</p>@enderror
    <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="attendance-heading">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-zinc-200 p-6 dark:border-zinc-800">
            <div><h2 id="attendance-heading" class="text-base font-semibold">{{ $record && $record->clock_out_at === null ? 'Current attendance session' : 'Today’s attendance' }}</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Work date {{ $workDate }} · {{ $record?->schedule_timezone ?? ($schedule?->timezone ?? 'Asia/Manila') }}</p></div>
            <x-leave.status :status="$record?->statusLabel() ?? ($dayStatus === 'Not due' ? 'Not clocked in' : $dayStatus)" />
        </div>
        <dl class="grid gap-6 p-6 sm:grid-cols-3">
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Clock in · Manila time</dt><dd class="mt-3 text-lg font-medium tabular-nums">{{ $record?->clock_in_at->setTimezone('Asia/Manila')->format('g:i:s A') ?? 'Not recorded' }}</dd>@if ($record)<p class="mt-1 text-xs text-zinc-500">{{ $record->clock_in_at->setTimezone('Asia/Manila')->format('M j, Y') }}</p>@endif</div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Clock out · Manila time</dt><dd class="mt-3 text-lg font-medium tabular-nums">{{ $record?->clock_out_at?->setTimezone('Asia/Manila')->format('g:i:s A') ?? 'Not recorded' }}</dd>@if ($record?->clock_out_at)<p class="mt-1 text-xs text-zinc-500">{{ $record->clock_out_at->setTimezone('Asia/Manila')->format('M j, Y') }}</p>@endif</div>
            <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">Working time</dt><dd class="mt-3 text-lg font-medium tabular-nums">{{ $record?->durationLabel() ?? 'Not recorded' }}</dd>@if ($record && ! $record->clock_out_at && ! $record->isOverdue())<p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($record->durationSeconds($now) / 3600, 2) }} hours elapsed since clock-in</p>@endif</div>
        </dl>
        <div class="flex flex-col gap-4 border-t border-zinc-200 p-6 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
            <p class="max-w-xl text-xs leading-6 text-zinc-500 dark:text-zinc-400">
                @if ($record?->isOverdue())This incomplete record needs an administrator’s correction before you can clock in again.
                @elseif ($record && ! $record->clock_out_at)Clock out when you finish work. Working time is finalized after clock-out.
                @elseif ($record?->clock_out_at)Your attendance for this work date is complete.
                @elseif (in_array($dayStatus, ['VL', 'SL'], true))You have approved full-day leave for this scheduled workday.
                @elseif ($dayStatus === 'Holiday')This work date is a configured non-working holiday.
                @elseif ($dayStatus === 'Rest day')This work date is a scheduled rest day.
                @elseif (! $schedule?->isConfigured())Ask your administrator to configure your work schedule.
                @elseif (! $canClockIn)Clock-in opens one hour before your shift and closes at its scheduled end. Rest days are excluded.
                @elseYour clock-in will use Attendify’s server time.@endif
            </p>
            @if ($record && ! $record->clock_out_at)
                <flux:button wire:click="clockOut" variant="primary" icon="arrow-right-start-on-rectangle" wire:loading.attr="disabled" wire:target="clockIn,clockOut" :disabled="$record->isOverdue()">Clock out</flux:button>
            @elseif (! $record)
                <flux:button wire:click="clockIn" variant="primary" icon="clock" wire:loading.attr="disabled" wire:target="clockIn,clockOut" :disabled="! $canClockIn">Clock in</flux:button>
            @endif
            <span wire:loading wire:target="clockIn,clockOut" role="status" class="text-xs text-zinc-500">Saving attendance…</span>
        </div>
    </section>
    <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="schedule-heading">
        <div class="flex items-center justify-between gap-4 border-b border-zinc-200 p-6 dark:border-zinc-800"><h2 id="schedule-heading" class="text-base font-semibold">Your work schedule</h2><flux:button :href="route('employee.schedule')" variant="ghost" size="sm" wire:navigate>Details</flux:button></div>
        <x-employee.schedule-details :schedule="$schedule" />
    </section>
    <section class="min-w-0 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="recent-heading">
        <div class="flex items-center justify-between gap-4 p-6"><h2 id="recent-heading" class="text-base font-semibold">Recent attendance</h2><flux:button :href="route('employee.attendance')" variant="ghost" size="sm" wire:navigate>View history</flux:button></div>
        @if ($recentRecords->isNotEmpty())<x-attendance.table :records="$recentRecords" />
        @else<x-admin.empty-state title="No attendance records yet" description="Your clock-ins and clock-outs will appear here once you begin recording attendance." />@endif
    </section>
</div>
