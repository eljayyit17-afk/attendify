<?php

use App\Livewire\Admin\EmployeeDirectory;
use Livewire\Attributes\Title;

new #[Title('Employees')] class extends EmployeeDirectory {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="Employees" description="Manage employee profiles, account access, and onboarding.">
        <flux:button wire:click="createEmployee" variant="primary" icon="plus">Add employee</flux:button>
    </x-admin.page-header>

    @if ($notice || $invitationNotice)
        <div role="status" aria-live="polite" class="rounded-md border border-zinc-200 bg-white px-4 py-3 text-sm leading-6 dark:border-zinc-800 dark:bg-zinc-900">
            @if ($notice)<p class="font-medium">{{ $notice }}</p>@endif
            @if ($invitationNotice)<p class="text-zinc-600 dark:text-zinc-400">{{ $invitationNotice }}</p>@endif
        </div>
    @endif

    <section aria-label="Employee directory" class="min-w-0 rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 p-4 sm:p-6 xl:flex-row xl:items-end xl:justify-between">
            <div><h2 class="text-base font-semibold">Employee directory</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ number_format($employees->total()) }} {{ $employees->total() === 1 ? 'employee' : 'employees' }} matching current filters</p></div>
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row">
                <flux:input wire:model.live.debounce.300ms="search" type="search" name="employee-search" autocomplete="off" icon="magnifying-glass" label="Search employees" placeholder="Name, email, ID, or department…" class="sm:w-72" />
                <flux:select wire:model.live="status" label="Account status" class="sm:w-40">
                    <option value="">All accounts</option><option value="active">Active</option><option value="inactive">Inactive</option>
                </flux:select>
            </div>
        </div>
        <div wire:loading.delay wire:target="search,status,gotoPage,nextPage,previousPage" role="status" class="px-6 pb-3 text-xs text-zinc-500">Updating directory…</div>
        @if ($employees->isNotEmpty())
            <div class="max-w-full overflow-x-auto rounded-b-lg" tabindex="0" role="region" aria-label="Employee directory table, scroll horizontally for more columns">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Employee profiles and account actions</caption>
                    <thead class="border-y border-zinc-200 bg-zinc-50 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
                        <tr><th scope="col" class="px-6 py-3 font-medium">Employee</th><th scope="col" class="px-6 py-3 font-medium">Employment</th><th scope="col" class="px-6 py-3 font-medium">Schedule</th><th scope="col" class="px-6 py-3 font-medium">Account</th><th scope="col" class="px-6 py-3 text-right font-medium">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($employees as $employee)
                            <tr wire:key="directory-{{ $employee->id }}">
                                <td class="min-w-56 max-w-80 px-6 py-4 align-top"><div class="font-medium break-words">{{ $employee->name }}</div><div class="mt-1 break-all text-xs text-zinc-500 dark:text-zinc-400">{{ $employee->email }}</div><div class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">{{ $employee->employeeProfile?->employee_id ?? 'Profile incomplete' }}</div></td>
                                <td class="min-w-48 px-6 py-4 align-top"><div>{{ $employee->employeeProfile?->position ?? 'Position not set' }}</div><div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $employee->employeeProfile?->department ?? 'Department not set' }}</div><div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $employee->employeeProfile?->hire_date ? 'Hired '.$employee->employeeProfile->hire_date->format('M j, Y') : 'Hire date not set' }}</div></td>
                                <td class="min-w-36 px-6 py-4 align-top text-xs text-zinc-600 dark:text-zinc-400">{{ $employee->employeeProfile?->schedule?->name ?? 'Unassigned' }}</td>
                                <td class="min-w-40 px-6 py-4 align-top"><span class="inline-flex items-center gap-2 text-xs {{ $employee->is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-400' }}"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $employee->is_active ? 'Active' : 'Inactive' }}</span><div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $employee->requires_password_setup ? 'Awaiting password setup' : 'Password configured' }}</div></td>
                                <td class="px-6 py-4 text-right align-top">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="'Actions for '.$employee->name" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="editEmployee({{ $employee->id }})" icon="pencil-square">Edit employee</flux:menu.item>
                                            @if ($employee->is_active && $employee->requires_password_setup)<flux:menu.item wire:click="resendInvitation({{ $employee->id }})" icon="envelope">Send invitation</flux:menu.item>@endif
                                            <flux:menu.separator />
                                            <flux:menu.item wire:click="confirmStatus({{ $employee->id }})" :icon="$employee->is_active ? 'no-symbol' : 'check-circle'">{{ $employee->is_active ? 'Deactivate account' : 'Activate account' }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 p-4 sm:p-6 dark:border-zinc-800">{{ $employees->links() }}</div>
        @else
            <x-admin.empty-state icon="users" :title="$search !== '' || $status !== '' ? 'No matching employees' : 'No employees yet'" :description="$search !== '' || $status !== '' ? 'Try a different search or account status.' : 'Add your first employee to create their profile and send a secure password setup invitation.'" />
        @endif
    </section>

    <flux:modal wire:model="showEditor" class="w-full min-w-0! max-w-[calc(100vw-2rem)]! sm:max-w-2xl!">
        <form wire:submit="saveEmployee" class="space-y-6">
            <div><flux:heading size="lg">{{ $editingId ? 'Edit employee' : 'Add employee' }}</flux:heading><flux:text class="mt-2">{{ $editingId ? 'Update employment details. Changing the email requires password setup again.' : 'Create an employee profile. A password setup link will be emailed to the employee.' }}</flux:text></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.name" label="Full name" autocomplete="name" required maxlength="255" />
                <flux:input wire:model="form.email" label="Email address" type="email" autocomplete="email" required maxlength="255" />
                <flux:input wire:model="form.employeeCode" label="Employee ID" description="Leave blank to generate an ID. Letters, numbers, hyphens, and underscores." maxlength="32" />
                <flux:input wire:model="form.hireDate" label="Hire date" type="date" required />
                <flux:input wire:model="form.department" label="Department" required maxlength="100" />
                <flux:input wire:model="form.position" label="Position" required maxlength="100" />
                <div class="sm:col-span-2"><flux:select wire:model="form.scheduleId" label="Schedule assignment (optional)">
                    <option value="">No schedule assigned</option>
                    @foreach ($schedules as $schedule)<option value="{{ $schedule->id }}">{{ $schedule->name }}</option>@endforeach
                </flux:select>
                @if ($schedules->isEmpty())<p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">No schedules have been configured. You can add an assignment later.</p>@endif</div>
            </div>
            <div class="flex flex-wrap justify-end gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                <flux:button x-on:click="$wire.showEditor = false" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveEmployee">{{ $editingId ? 'Save changes' : 'Create employee' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showStatusConfirmation" class="w-full min-w-0! max-w-[calc(100vw-2rem)]! sm:max-w-md!">
        <div class="space-y-6"><div><flux:heading size="lg">{{ $targetActive ? 'Activate employee account?' : 'Deactivate employee account?' }}</flux:heading><flux:text class="mt-2">{{ $targetActive ? 'The employee can access Attendify once their password is configured.' : 'The employee will lose access immediately. Existing sessions and password setup links will be revoked. Their profile and records will be retained.' }}</flux:text></div>
            <div class="flex justify-end gap-3"><flux:button x-on:click="$wire.showStatusConfirmation = false" variant="ghost">Cancel</flux:button><flux:button wire:click="changeStatus" wire:loading.attr="disabled" wire:target="changeStatus" :variant="$targetActive ? 'primary' : 'danger'">{{ $targetActive ? 'Activate account' : 'Deactivate account' }}</flux:button></div>
        </div>
    </flux:modal>
</div>
