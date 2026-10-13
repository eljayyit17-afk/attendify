@props(['periods', 'grouping'])
@php
    $series = ['Present' => '#047857', 'Late' => '#b45309', 'Absent' => '#b91c1c', 'VL' => '#6d4bb8', 'SL' => '#0369a1'];
    $totals = array_map(fn ($counts) => array_sum(array_intersect_key($counts, $series)), $periods);
    $max = max([1, ...array_values($totals)]);
    $width = max(640, count($periods) * 28 + 80);
    $step = ($width - 80) / max(1, count($periods));
@endphp
@if (array_sum($totals) > 0)
    <figure>
        <div class="flex flex-wrap gap-x-6 gap-y-2 px-6 pt-6 text-xs text-zinc-600 dark:text-zinc-400">@foreach ($series as $state => $color)<span class="flex items-center gap-2"><span class="size-2 rounded-sm" style="background-color: {{ $color }}" aria-hidden="true"></span>{{ $state }}</span>@endforeach</div>
        <div class="max-w-full overflow-x-auto p-6" tabindex="0" role="region" aria-label="Attendance trend chart, scroll for more periods">
            <svg viewBox="0 0 {{ $width }} 240" style="width: {{ $width }}px; height: 240px" role="img" aria-label="{{ ucfirst($grouping) }} employee-workday counts. Exact values are available in the period summary table.">
                @foreach ([0, 0.5, 1] as $fraction)<line x1="50" y1="{{ 200 - 160 * $fraction }}" x2="{{ $width - 20 }}" y2="{{ 200 - 160 * $fraction }}" stroke="currentColor" class="text-zinc-200 dark:text-zinc-700" /><text x="40" y="{{ 204 - 160 * $fraction }}" text-anchor="end" fill="currentColor" class="text-zinc-500 dark:text-zinc-400" font-size="11">{{ number_format($max * $fraction, $max % 2 && $fraction === 0.5 ? 1 : 0) }}</text>@endforeach
                @foreach ($periods as $period => $counts)
                    @php($height = 0)
                    @foreach ($series as $state => $color)
                        @php($barHeight = 160 * ($counts[$state] ?? 0) / $max)
                        @if ($barHeight > 0)<rect x="{{ 50 + $loop->parent->index * $step + 3 }}" y="{{ 200 - $height - $barHeight }}" width="{{ max(4, min(22, $step - 6)) }}" height="{{ $barHeight }}" fill="{{ $color }}"><title>{{ $period }}: {{ $state }} {{ $counts[$state] ?? 0 }}</title></rect>@endif
                        @php($height += $barHeight)
                    @endforeach
                    @if ($loop->index % max(1, (int) ceil(count($periods) / 12)) === 0)<text x="{{ 50 + $loop->index * $step + 3 }}" y="225" fill="currentColor" class="text-zinc-500 dark:text-zinc-400" font-size="10">{{ $period }}</text>@endif
                @endforeach
            </svg>
        </div>
        <figcaption class="border-t border-zinc-200 px-6 py-3 text-xs leading-6 text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">Counts are employee-workdays, with all selected filters applied. Weekly periods begin Monday. Rest days, holidays, pending shifts, and untracked days are excluded from these five series.</figcaption>
    </figure>
@else
    <x-admin.empty-state icon="chart-bar" title="No tracked attendance trends" description="No matching attendance, approved leave, or completed-shift absences are available in this range." />
@endif
