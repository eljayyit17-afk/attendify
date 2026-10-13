<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="main-content" role="main" tabindex="-1" class="p-4! sm:p-8!">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
