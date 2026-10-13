<?php

use App\Models\User;
use App\Models\AttendanceSession;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Overview')] class extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin' && auth()->user()?->is_active, 403);
    }

    public function with(): array
    {
        $counts = User::query()->where('role', 'employee')
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active')
            ->toBase()->first();

        return [
            'pendingLeave' => \App\Models\LeaveRequest::where('status', 'pending')->count(),
            'workdayCounts' => app(\App\Services\WorkdayReport::class)->rows(now('Asia/Manila')->toDateString(), now('Asia/Manila')->toDateString())->countBy('status')->all(),
            'recentAttendance' => AttendanceSession::query()->with('user:id,name')->latest('id')->limit(5)->get(),
            'totalEmployees' => (int) $counts->total,
            'activeEmployees' => (int) $counts->active,
            'employees' => User::query()->where('role', 'employee')
                ->select(['id', 'name', 'email', 'is_active', 'created_at'])
                ->latest('id')->limit(5)->get(),
        ];
    }
};
?>

<div class="mx-auto w-full min-w-0 max-w-7xl space-y-8">
    <x-admin.page-header title="Workforce overview" description="A clear view of your employee accounts and attendance workspace.">
        <flux:button :href="route('admin.employees')" icon="users" variant="primary" wire:navigate>View employees</flux:button>
    </x-admin.page-header>

    <section aria-label="Workforce summary" class="grid divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
        @foreach (['Total employees' => [$totalEmployees, 'All employee accounts'], 'Active accounts' => [$activeEmployees, 'Employees with account access'], 'Inactive accounts' => [$totalEmployees - $activeEmployees, 'Employees without account access']] as $label => [$value, $detail])
            <div class="p-6">
                <h2 class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ $label }}</h2>
                <p class="mt-4 text-3xl font-semibold tracking-tight tabular-nums">{{ number_format($value) }}</p>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $detail }}</p>
            </div>
        @endforeach
    </section>

    <section class="flex flex-wrap items-center gap-x-8 gap-y-4 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900" aria-label="Current work date attendance">
        <div><h2 class="text-sm font-medium">Work date {{ now('Asia/Manila')->toDateString() }}</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Absence is counted only after shift end.</p></div>
        @foreach (['Present', 'Late', 'Absent', 'VL', 'SL'] as $state)<div><p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $state }}</p><p class="mt-2 text-xl font-semibold tabular-nums">{{ number_format($workdayCounts[$state] ?? 0) }}</p></div>@endforeach
        <flux:button :href="route('admin.reports')" variant="ghost" size="sm" wire:navigate>View report</flux:button>
    </section>
    <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-8">
            <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="employees-heading">
                <div class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div><h2 id="employees-heading" class="text-base font-semibold">Employee directory</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Most recently added employee accounts</p></div>
                    <flux:button :href="route('admin.employees')" variant="ghost" size="sm" wire:navigate>View all</flux:button>
                </div>
                @if ($employees->isNotEmpty())
                    <x-admin.employee-table :employees="$employees" />
                @else
                    <x-admin.empty-state icon="users" title="No employees yet" description="Employee accounts will appear here once they have been added to Attendify." />
                @endif
            </section>
            <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="attendance-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 p-6 dark:border-zinc-800"><h2 id="attendance-heading" class="text-base font-semibold">Recent attendance</h2><flux:button :href="route('admin.attendance')" variant="ghost" size="sm" wire:navigate>View register</flux:button></div>
                @if ($recentAttendance->isNotEmpty())
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">@foreach ($recentAttendance as $attendance)<li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4"><div><p class="text-sm font-medium">{{ $attendance->user->name }}</p><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Work date {{ $attendance->work_date }} · {{ $attendance->schedule_name }}</p></div><span class="text-xs text-zinc-600 dark:text-zinc-400">{{ $attendance->statusLabel() }}</span></li>@endforeach</ul>
                @else<x-admin.empty-state title="No attendance records yet" description="Employee clock-ins and clock-outs will appear here after work schedules are assigned." />@endif
            </section>
        </div>
        <aside class="min-w-0 space-y-6" aria-label="Workspace tools">
            <section class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="text-sm font-semibold">Pending leave approvals</h2><p class="mt-4 text-2xl font-semibold tabular-nums">{{ number_format($pendingLeave) }}</p><flux:button :href="route('admin.leave')" variant="ghost" size="sm" class="mt-4" wire:navigate>Review requests</flux:button></section>
            <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 p-6 dark:border-zinc-800"><h2 class="text-base font-semibold">Workforce tools</h2><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Plan work and review attendance</p></div>
                @foreach (['schedules' => ['calendar-days', 'Work schedules', 'Manage shift hours and working days.'], 'reports' => ['chart-bar', 'Attendance reports', 'Review attendance, leave, and completed-shift absences.']] as $section => [$icon, $label, $description])
                    <a href="{{ route('admin.'.$section) }}" wire:navigate class="flex items-start gap-3 border-b border-zinc-100 p-6 transition-colors duration-150 last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"><flux:icon :name="$icon" class="mt-0.5 size-5 shrink-0 text-zinc-500" /><div><h3 class="text-sm font-medium">{{ $label }}</h3><p class="mt-2 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ $description }}</p></div><flux:icon.chevron-right class="ml-auto mt-1 size-4 shrink-0 text-zinc-400" /></a>
                @endforeach
            </section>
            <div class="px-1"><h2 class="text-sm font-medium">About this overview</h2><p class="mt-2 text-xs leading-6 text-zinc-500 dark:text-zinc-400">Account status reflects access to Attendify. It does not indicate whether an employee is present at work.</p></div>
        </aside>
    </div>
</div>
