<?php

use App\Livewire\Admin\WorkSchedules;
use Livewire\Attributes\Title;

new #[Title('Schedules')] class extends WorkSchedules {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="Schedules" description="Configure shift hours, timezones, and working days. Assign schedules through the employee directory.">
        <flux:button wire:click="createSchedule" variant="primary" icon="plus">Add schedule</flux:button>
    </x-admin.page-header>
    @if ($notice)<p role="status" class="rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">{{ $notice }}</p>@endif
    <section class="min-w-0 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-label="Work schedules">
        <h2 class="sr-only">Configured schedules</h2>
        @if ($schedules->isNotEmpty())
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Work schedules table">
                <table class="w-full text-left text-sm"><caption class="sr-only">Shift times use the schedule timezone.</caption>
                    <thead class="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400"><tr><th scope="col" class="px-6 py-3 font-medium">Schedule</th><th scope="col" class="px-6 py-3 font-medium">Hours</th><th scope="col" class="px-6 py-3 font-medium">Working days</th><th scope="col" class="px-6 py-3 font-medium">Assigned employees</th><th scope="col" class="px-6 py-3 font-medium">Actions</th></tr></thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($schedules as $schedule)<tr wire:key="schedule-{{ $schedule->id }}">
                            <td class="px-6 py-4"><div class="font-medium">{{ $schedule->name }}</div><div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $schedule->timezone }}</div></td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs">{{ $schedule->isConfigured() ? substr($schedule->start_time, 0, 5).' – '.substr($schedule->end_time, 0, 5) : 'Needs configuration' }}</td>
                            <td class="px-6 py-4 text-xs">{{ $schedule->daysLabel() ?: 'Not set' }}</td><td class="px-6 py-4 text-xs tabular-nums">{{ $schedule->employee_profiles_count }}</td>
                            <td class="px-6 py-4"><flux:button wire:click="editSchedule({{ $schedule->id }})" variant="ghost" size="sm" :aria-label="'Edit schedule '.$schedule->name">Edit</flux:button></td>
                        </tr>@endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 p-6 dark:border-zinc-800">{{ $schedules->links() }}</div>
        @else<x-admin.empty-state icon="calendar-days" title="No work schedules yet" description="Create a schedule with shift hours and working days, then assign it to employees in the directory." />@endif
    </section>
    <flux:modal wire:model="showEditor" class="w-full min-w-0! max-w-[calc(100vw-2rem)]! sm:max-w-xl!">
        <form wire:submit="saveSchedule" class="space-y-6">
            <div><flux:heading size="lg">{{ $editingId ? 'Edit schedule' : 'Add schedule' }}</flux:heading><flux:text class="mt-2">An end time earlier than the start time creates an overnight shift. Maximum shift length: 16 hours.</flux:text></div>
            <flux:input wire:model="name" label="Schedule name" required maxlength="100" />
            <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="startTime" type="time" label="Shift start" required /><flux:input wire:model="endTime" type="time" label="Shift end" required /></div>
            <flux:input wire:model="timezone" label="Schedule timezone" placeholder="Asia/Manila" description="Use an IANA timezone, such as Asia/Manila or America/New_York." required />
            <flux:checkbox.group wire:model="workingDays" label="Working days">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">@foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $day => $label)<flux:checkbox :value="(string) $day" :label="$label" />@endforeach</div>
            </flux:checkbox.group>
            <div class="flex justify-end gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800"><flux:button x-on:click="$wire.showEditor = false" variant="ghost">Cancel</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveSchedule">Save schedule</flux:button></div>
        </form>
    </flux:modal>
</div>
