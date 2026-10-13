<?php
use App\Livewire\Admin\HolidayLibrary;
use Livewire\Attributes\Title;
new #[Title('Holidays')] class extends HolidayLibrary {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8"><x-admin.page-header title="Holiday calendar" description="Configure non-working holidays explicitly for the matching schedule timezone. No regional holiday list is assumed." />
    @if ($notice)<p role="status" class="rounded-md border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">{{ $notice }}</p>@endif
    <form wire:submit="addHoliday" class="grid items-end gap-4 rounded-lg border border-zinc-200 bg-white p-6 sm:grid-cols-2 xl:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-900"><flux:input wire:model="name" label="Holiday name" required /><flux:input type="date" wire:model="date" label="Work date" required /><flux:input wire:model="timezone" label="Schedule timezone" required /><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Add holiday</flux:button></form>
    <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"><h2 class="p-6 text-base font-semibold">Configured holidays</h2>@forelse ($holidays as $holiday)<div class="flex flex-wrap items-center justify-between gap-4 border-t border-zinc-200 px-6 py-4 dark:border-zinc-800"><div class="text-sm"><p class="font-medium">{{ $holiday->name }}</p><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $holiday->holiday_date }} · {{ $holiday->timezone }}</p></div><flux:button wire:click="removeHoliday({{ $holiday->id }})" wire:confirm="Remove this holiday? Historical absence reports will be recalculated." variant="ghost" size="sm">Remove</flux:button></div>@empty<x-admin.empty-state icon="calendar-days" title="No configured holidays" description="Add the non-working dates that apply to your organization." />@endforelse<div class="p-6">{{ $holidays->links() }}</div></section>
</div>
