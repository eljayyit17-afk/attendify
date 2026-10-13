@props(['records', 'admin' => false])
<div class="max-w-full overflow-x-auto" tabindex="0" role="region" aria-label="Attendance records table">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Attendance times are displayed in Asia/Manila; work dates follow the assigned shift timezone.</caption>
        <thead class="border-y border-zinc-200 bg-zinc-50 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400">
            <tr>
                @if ($admin)<th scope="col" class="px-6 py-3 font-medium">Employee</th>@endif
                <th scope="col" class="px-6 py-3 font-medium">Work date / shift</th><th scope="col" class="px-6 py-3 font-medium">Clock in</th><th scope="col" class="px-6 py-3 font-medium">Clock out</th><th scope="col" class="px-6 py-3 font-medium">Working time</th><th scope="col" class="px-6 py-3 font-medium">Status</th>
                @if ($admin)<th scope="col" class="px-6 py-3 font-medium">Actions</th>@endif
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($records as $record)
                <tr wire:key="attendance-{{ $record->id }}">
                    @if ($admin)<td class="px-6 py-4">{{ $record->user->name }}</td>@endif
                    <td class="px-6 py-4"><div class="whitespace-nowrap font-medium">{{ $record->work_date }}</div><div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $record->schedule_name }}</div><div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $record->schedule_timezone }}</div></td>
                    <td class="whitespace-nowrap px-6 py-4 text-xs tabular-nums">{{ $record->clock_in_at->setTimezone('Asia/Manila')->format('M j, g:i:s A') }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-xs tabular-nums">{{ $record->clock_out_at?->setTimezone('Asia/Manila')->format('M j, g:i:s A') ?? 'Not recorded' }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-xs tabular-nums">{{ $record->durationLabel() }}</td>
                    <td class="whitespace-nowrap px-6 py-4 text-xs {{ $record->isOverdue() ? 'text-amber-700 dark:text-amber-400' : 'text-zinc-600 dark:text-zinc-400' }}">{{ $record->statusLabel() }}</td>
                    @if ($admin)<td class="px-6 py-4"><flux:button wire:click="editRecord({{ $record->id }})" variant="ghost" size="sm" :aria-label="'Correct attendance for '.$record->user->name.' on '.$record->work_date">Correct</flux:button></td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
