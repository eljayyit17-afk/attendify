<?php

use App\Livewire\Employee\PersonalHistory;
use Livewire\Attributes\Title;

new #[Title('My attendance')] class extends PersonalHistory {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="My attendance" description="Review your recorded workdays. Times are displayed in Asia/Manila; work dates follow your shift’s timezone." />
    <section class="min-w-0 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-label="Personal attendance history">
        <h2 class="sr-only">Attendance history</h2>
        <div class="flex flex-wrap items-end gap-4 p-6">
            <flux:input type="date" name="from-work-date" autocomplete="off" wire:model.live="fromDate" label="From work date" class="sm:max-w-48" />
            <flux:input type="date" name="to-work-date" autocomplete="off" wire:model.live="toDate" label="To work date" class="sm:max-w-48" />
            <flux:button wire:click="resetFilters" variant="ghost">Clear dates</flux:button>
            <span wire:loading.delay role="status" class="text-xs text-zinc-500">Updating history…</span>
        </div>
        @if ($records->isNotEmpty())
            <x-attendance.table :records="$records" />
            <div class="border-t border-zinc-200 p-6 dark:border-zinc-800">{{ $records->links() }}</div>
        @else<x-admin.empty-state title="No records for these dates" description="Choose a different date range, or return to your dashboard to record attendance." />@endif
    </section>
</div>
