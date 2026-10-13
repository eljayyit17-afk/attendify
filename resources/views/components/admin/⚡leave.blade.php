<?php
use App\Livewire\Admin\LeaveReview;
use Livewire\Attributes\Title;
new #[Title('Leave approvals')] class extends LeaveReview {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="Leave approvals" description="Review full-day leave requests. Decisions record your identity and server time." />
    @if ($notice)<p role="status" class="rounded-md border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">{{ $notice }}</p>@endif
    <section class="min-w-0 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"><div class="flex flex-wrap items-center justify-between gap-4 p-6"><h2 class="text-base font-semibold">Leave requests</h2><flux:select wire:model.live="status" label="Request status" class="sm:max-w-48"><option value="">All requests</option>@foreach (['pending','approved','rejected','cancelled'] as $state)<option value="{{ $state }}">{{ ucfirst($state) }}</option>@endforeach</flux:select></div>
        @if ($requests->isNotEmpty())<x-leave.table :requests="$requests" :admin="true" /><div class="p-6">{{ $requests->links() }}</div>@else<x-admin.empty-state icon="calendar-days" title="No requests in this view" description="Employee leave requests will appear here when they match the selected status." />@endif
    </section>
    <flux:modal wire:model="showReview" class="w-full min-w-0! max-w-[calc(100vw-2rem)]! sm:max-w-xl!">
        <div class="space-y-6"><flux:heading size="lg">Review leave request</flux:heading>
            @if ($reviewing)<p class="text-sm font-medium">{{ $reviewing->user->name }} · {{ $reviewing->leave_type }}<br>{{ $reviewing->start_date }} – {{ $reviewing->end_date }}</p><p class="break-words text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $reviewing->reason }}</p>@endif
            @error('decision')<p role="alert" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>@enderror
            <flux:textarea wire:model="remarks" label="Decision remarks (optional)" maxlength="500" />
            <div class="flex flex-wrap justify-end gap-3"><flux:button x-on:click="$wire.showReview = false" variant="ghost">Close</flux:button><flux:button wire:click="decide('rejected')" variant="danger" wire:loading.attr="disabled">Reject</flux:button><flux:button wire:click="decide('approved')" variant="primary" wire:loading.attr="disabled">Approve</flux:button></div>
        </div>
    </flux:modal>
</div>
