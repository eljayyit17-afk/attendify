<?php

use App\Livewire\Employee\WorkProfile;
use Livewire\Attributes\Title;

new #[Title('My profile')] class extends WorkProfile {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="My profile" description="Your employee details are maintained by your administrator. Contact them to request a correction." />
    <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="profile-heading">
        <h2 id="profile-heading" class="border-b border-zinc-200 p-6 text-base font-semibold dark:border-zinc-800">Employee information</h2>
        <dl class="grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['Full name' => $employee->name, 'Email address' => $employee->email, 'Employee ID' => $employee->employeeProfile?->employee_id, 'Department' => $employee->employeeProfile?->department, 'Position' => $employee->employeeProfile?->position, 'Hire date' => $employee->employeeProfile?->hire_date?->format('M j, Y')] as $label => $value)
                <div class="min-w-0"><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</dt><dd class="mt-2 break-words text-sm font-medium">{{ $value ?? 'Not provided' }}</dd></div>
            @endforeach
        </dl>
    </section>
</div>
