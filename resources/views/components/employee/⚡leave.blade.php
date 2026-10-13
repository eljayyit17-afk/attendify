<?php
use App\Livewire\Employee\LeaveWorkspace;
use Livewire\Attributes\Title;
new #[Title('My leave')] class extends LeaveWorkspace {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="My leave" description="Request full-day vacation or sick leave and track your administrator’s decision."><flux:button x-on:click="$wire.showRequest = true" variant="primary" icon="plus">Request leave</flux:button></x-admin.page-header>
    @if ($notice)<p role="status" class="rounded-md border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-800 dark:bg-zinc-900">{{ $notice }}</p>@endif
    @error('decision')<p role="alert" class="text-sm text-red-700 dark:text-red-400">{{ $message }}</p>@enderror
    <section class="min-w-0 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-label="Leave request history"><h2 class="p-6 text-base font-semibold">Request history</h2>
        @if ($requests->isNotEmpty())<x-leave.table :requests="$requests" /><div class="p-6">{{ $requests->links() }}</div>@else<x-admin.empty-state icon="calendar-days" title="No leave requests yet" description="Submit a request when you need a full day away from work." />@endif
    </section>
    <p class="text-xs leading-6 text-zinc-500 dark:text-zinc-400">Leave is recorded as full days. Paid status and balances are not assumed. Approved leave applies only to scheduled workdays; rest days and configured holidays do not count as leave days.</p>
    <flux:modal wire:model="showRequest" class="w-full min-w-0! max-w-[calc(100vw-2rem)]! sm:max-w-xl!">
        <form wire:submit="submit" class="space-y-6"><flux:heading size="lg">Request leave</flux:heading>
            <flux:select wire:model="type" label="Leave type"><option value="VL">Vacation leave (VL)</option><option value="SL">Sick leave (SL)</option></flux:select>
            <div class="grid gap-4 sm:grid-cols-2"><flux:input type="date" wire:model="from" label="Start work date" required /><flux:input type="date" wire:model="to" label="End work date" required /></div>
            <flux:textarea wire:model="reason" label="General reason" description="Use a brief administrative reason. Do not include diagnoses, medical records, or detailed health information." required maxlength="500" />
            <div class="flex justify-end gap-3"><flux:button x-on:click="$wire.showRequest = false" variant="ghost">Cancel</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submit">Submit request</flux:button></div>
        </form>
    </flux:modal>
</div>
