<?php

use App\Livewire\Employee\WorkProfile;
use Livewire\Attributes\Title;

new #[Title('My schedule')] class extends WorkProfile {};
?>
<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="My schedule" description="Your assigned shift hours and working days. Attendance times elsewhere in the portal are shown in Asia/Manila." />
    <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="assigned-heading">
        <h2 id="assigned-heading" class="border-b border-zinc-200 p-6 text-base font-semibold dark:border-zinc-800">Assigned schedule</h2>
        <x-employee.schedule-details :schedule="$employee->employeeProfile?->schedule" />
    </section>
    <div class="max-w-2xl text-sm leading-7 text-zinc-600 dark:text-zinc-400"><p>Clock-in is available from one hour before your scheduled start until the scheduled end. Overnight shifts belong to the date on which the shift starts, even when clock-in occurs after midnight.</p><p class="mt-4">Days outside your assigned working days are rest days. Attendify records one continuous session per work date. If a clock-out is missing for more than 24 hours, ask your administrator to correct the record.</p></div>
</div>
