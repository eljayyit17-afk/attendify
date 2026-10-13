<?php

use App\Livewire\Admin\AttendanceRegister;
use Livewire\Attributes\Title;

new #[Title('Attendance')] class extends AttendanceRegister {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="Attendance" description="Review recorded sessions and correct incomplete attendance. Times and correction inputs use Asia/Manila."><flux:button :href="route('admin.workdays')" variant="outline" wire:navigate>Filter workday statuses</flux:button></x-admin.page-header>
    @if ($notice)<p role="status" class="rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-900">{{ $notice }}</p>@endif
    <section class="min-w-0 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-label="Attendance register">
        <h2 class="sr-only">Attendance records</h2>
        @if ($records->isNotEmpty())<x-attendance.table :records="$records" :admin="true" /><div class="border-t border-zinc-200 p-6 dark:border-zinc-800">{{ $records->links() }}</div>
        @else<x-admin.empty-state title="No attendance records yet" description="Employee clock-ins and clock-outs will appear here after work schedules are assigned." />@endif
    </section>
    <flux:modal wire:model="showCorrection" class="w-full min-w-0! max-w-[calc(100vw-2rem)]! sm:max-w-2xl!">
        <form wire:submit="saveCorrection" class="space-y-6">
            <div><flux:heading size="lg">Correct attendance</flux:heading><flux:text class="mt-2">{{ $editingRecord?->user->name }} · Work date {{ $editingRecord?->work_date }}. Original shift details are retained. All inputs below use Asia/Manila.</flux:text></div>
            @error('correction')<p role="alert" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>@enderror
            <flux:input wire:model="clockInLocal" type="datetime-local" step="1" label="Actual clock-in · Manila time" required />
            <flux:input wire:model="clockOutLocal" type="datetime-local" step="1" label="Actual clock-out · Manila time" description="Leave blank only to reopen a recent record. Use the actual time worked; do not assume the shift end." />
            <flux:textarea wire:model="reason" label="Reason for correction" description="Explain what changed and why. At least 10 characters are required." required maxlength="1000" />
            <div class="flex justify-end gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800"><flux:button x-on:click="$wire.showCorrection = false" variant="ghost">Cancel</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveCorrection">Save correction</flux:button></div>
        </form>
        @if ($editingRecord && $editingRecord->corrections->isNotEmpty())
            <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-800"><h3 class="text-sm font-medium">Latest 10 corrections</h3><ol class="mt-4 space-y-6">
                @foreach ($editingRecord->corrections as $correction)<li class="text-xs leading-6"><p class="font-medium">{{ $correction->actor->name }} · {{ $correction->created_at->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}</p><p class="mt-1 break-words text-zinc-600 dark:text-zinc-400">{{ $correction->reason }}</p><p class="mt-1 break-words text-zinc-500 dark:text-zinc-400">Before (UTC): {{ $correction->before_values['clock_in_at'] }} / {{ $correction->before_values['clock_out_at'] ?? 'Open' }}<br>After (UTC): {{ $correction->after_values['clock_in_at'] }} / {{ $correction->after_values['clock_out_at'] ?? 'Open' }}</p></li>@endforeach
            </ol></div>
        @endif
    </flux:modal>
</div>
