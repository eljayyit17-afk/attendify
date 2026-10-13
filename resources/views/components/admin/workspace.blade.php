<x-layouts::app :title="$title">
    <div class="mx-auto max-w-7xl space-y-8">
        <x-admin.page-header :title="$title" :description="$description" />
        <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-label="{{ $title }} availability">
            <x-admin.empty-state :icon="$icon" :title="$emptyTitle" :description="$emptyDescription">
                <flux:button :href="route('admin.dashboard')" variant="outline" size="sm" wire:navigate>Back to overview</flux:button>
            </x-admin.empty-state>
        </section>
    </div>
</x-layouts::app>
