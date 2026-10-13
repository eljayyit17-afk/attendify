@props(['title', 'description'])
<header {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between') }}>
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $title }}</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
    </div>
    @if ($slot->isNotEmpty())<div class="shrink-0">{{ $slot }}</div>@endif
</header>
