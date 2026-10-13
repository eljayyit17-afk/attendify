@props(['icon' => 'clock', 'title', 'description'])
<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <div class="mb-4 flex size-12 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400"><flux:icon :name="$icon" class="size-6" /></div>
    <h3 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $title }}</h3>
    <p class="mt-2 max-w-sm text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    @if ($slot->isNotEmpty())<div class="mt-6">{{ $slot }}</div>@endif
</div>
