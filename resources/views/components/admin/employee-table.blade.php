@props(['employees'])
<div class="max-w-full overflow-x-auto" tabindex="0" role="region" aria-label="Employee accounts table">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Employee accounts and access status</caption>
        <thead class="border-y border-zinc-200 bg-zinc-50 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
            <tr><th scope="col" class="px-6 py-3 font-medium">Employee</th><th scope="col" class="px-6 py-3 font-medium">Account status</th><th scope="col" class="hidden px-6 py-3 font-medium sm:table-cell">Added</th></tr>
        </thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($employees as $employee)
                <tr wire:key="employee-{{ $employee->id }}">
                    <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-zinc-100 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" aria-hidden="true">{{ $employee->initials() }}</span><div><div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $employee->name }}</div><div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $employee->email }}</div></div></div></td>
                    <td class="px-6 py-4"><span class="inline-flex items-center gap-2 text-xs {{ $employee->is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $employee->is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td class="hidden whitespace-nowrap px-6 py-4 text-xs text-zinc-500 sm:table-cell dark:text-zinc-400">{{ $employee->created_at?->format('M j, Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
