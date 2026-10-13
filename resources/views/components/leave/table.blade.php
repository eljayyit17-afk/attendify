@props(['requests', 'admin' => false])
<div class="max-w-full overflow-x-auto" tabindex="0" role="region" aria-label="Leave requests table">
    <table class="w-full text-left text-sm"><caption class="sr-only">Full-day leave requests and decisions</caption>
        <thead class="border-y border-zinc-200 bg-zinc-50 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/50 dark:text-zinc-400"><tr>
            @if ($admin)<th scope="col" class="px-6 py-3 font-medium">Employee</th>@endif
            <th scope="col" class="px-6 py-3 font-medium">Leave / dates</th><th scope="col" class="px-6 py-3 font-medium">Reason</th><th scope="col" class="px-6 py-3 font-medium">Status / decision</th><th scope="col" class="px-6 py-3 font-medium">Actions</th>
        </tr></thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">@foreach ($requests as $leave)<tr wire:key="leave-{{ $leave->id }}">
            @if ($admin)<td class="px-6 py-4">{{ $leave->user->name }}</td>@endif
            <td class="whitespace-nowrap px-6 py-4"><p class="font-medium">{{ $leave->leave_type === 'VL' ? 'Vacation leave' : 'Sick leave' }}</p><p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $leave->start_date }} – {{ $leave->end_date }}</p></td>
            <td class="min-w-48 max-w-sm break-words px-6 py-4 text-xs leading-6">{{ $leave->reason }}</td>
            <td class="min-w-48 px-6 py-4"><x-leave.status :status="$leave->status" />@if ($leave->approver)<p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $leave->approver->name }}<br>{{ $leave->decided_at?->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif @if ($leave->remarks)<p class="mt-2 break-words text-xs text-zinc-600 dark:text-zinc-400">{{ $leave->remarks }}</p>@endif</td>
            <td class="px-6 py-4">@if ($admin && $leave->status === 'pending')<flux:button wire:click="review({{ $leave->id }})" variant="ghost" size="sm">Review</flux:button>@elseif (! $admin && in_array($leave->status, ['pending', 'approved']))<flux:button wire:click="cancelRequest({{ $leave->id }})" wire:confirm="Cancel this leave request?" variant="ghost" size="sm">Cancel</flux:button>@endif</td>
        </tr>@endforeach</tbody>
    </table>
</div>
