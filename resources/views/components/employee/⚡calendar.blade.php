<?php
use App\Livewire\Employee\WorkCalendar;
use Livewire\Attributes\Title;
new #[Title('My calendar')] class extends WorkCalendar {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="My work calendar" description="Attendance and approved leave by work date. Pending leave is shown separately.">
        <div class="flex items-center gap-3"><flux:button wire:click="moveMonth(-1)" icon="chevron-left" variant="ghost" aria-label="Previous month" /><span class="text-sm font-medium">{{ $first->format('F Y') }}</span><flux:button wire:click="moveMonth(1)" icon="chevron-right" variant="ghost" aria-label="Next month" /></div>
    </x-admin.page-header>
    <section aria-label="Monthly work calendar" class="rounded-lg border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="sr-only">Work dates</h2>
        <div class="hidden grid-cols-7 gap-2 pb-3 text-xs text-zinc-500 xl:grid">@foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<div>{{ $day }}</div>@endforeach</div>
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-7">
            @for ($i = 1; $i < $first->isoWeekday(); $i++)<div class="hidden xl:block" aria-hidden="true"></div>@endfor
            @foreach ($days as $day)<div class="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-md border border-zinc-200 p-3 xl:min-h-28 xl:flex-col xl:items-start dark:border-zinc-800"><time datetime="{{ $day['date'] }}" class="text-sm font-medium"><span class="xl:hidden">{{ \Carbon\CarbonImmutable::parse($day['date'])->format('D j') }}</span><span class="hidden xl:inline">{{ \Carbon\CarbonImmutable::parse($day['date'])->format('j') }}</span></time><x-leave.status :status="$day['status']" />@if ($day['pending'])<span class="text-xs text-amber-700 dark:text-amber-400">Leave pending</span>@endif</div>@endforeach
        </div>
    </section>
    <p class="text-xs leading-6 text-zinc-500 dark:text-zinc-400">Absence is calculated only after the scheduled shift ends. Unscheduled dates include days before reliable schedule tracking began. Overnight work belongs to the shift-start date. No paid-leave balance is implied.</p>
</div>
