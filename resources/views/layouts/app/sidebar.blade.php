<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script>
            if (!localStorage.getItem('attendify.appearance.initialized')) {
                if (!localStorage.getItem('flux.appearance')) localStorage.setItem('flux.appearance', 'light');
                localStorage.setItem('attendify.appearance.initialized', 'true');
            }
        </script>
        @include('partials.head')
    </head>
    <body class="attendify-shell min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:bg-white focus:p-4 focus:text-zinc-900">Skip to content</a>
        <flux:sidebar sticky collapsible="mobile" class="w-60! border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header>
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-md px-2 py-2" aria-label="Attendify home">
                    <span class="flex size-8 items-center justify-center rounded-md bg-violet-700 text-white"><flux:icon.clock class="size-5" /></span>
                    <span class="text-lg font-semibold tracking-tight">Attendify</span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>
            <div class="mx-2 border-b border-zinc-200 pb-4 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">{{ auth()->user()->role === 'admin' ? __('Administration workspace') : __('Employee workspace') }}</div>
            <flux:sidebar.nav aria-label="Main navigation">
                <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" :current="request()->routeIs('dashboard', 'admin.dashboard', 'employee.dashboard')" :aria-current="request()->routeIs('dashboard', 'admin.dashboard', 'employee.dashboard') ? 'page' : null" wire:navigate>Overview</flux:sidebar.item>
                @if (auth()->user()->role === 'admin')
                    @foreach (['employees' => ['users', 'Employees'], 'attendance' => ['clock', 'Attendance'], 'leave' => ['calendar-days', 'Leave approvals'], 'schedules' => ['calendar-days', 'Schedules'], 'holidays' => ['calendar-days', 'Holidays'], 'reports' => ['chart-bar', 'Reports']] as $section => [$icon, $label])
                        @php($isCurrent = request()->routeIs('admin.'.$section) || ($section === 'reports' && request()->routeIs('admin.workdays')))
                        <flux:sidebar.item :icon="$icon" :href="route('admin.'.$section)" :current="$isCurrent" :aria-current="$isCurrent ? 'page' : null" wire:navigate>{{ $label }}</flux:sidebar.item>
                    @endforeach
                @else
                    @foreach (['attendance' => ['clock', 'My attendance'], 'leave' => ['calendar-days', 'My leave'], 'calendar' => ['calendar-days', 'My calendar'], 'schedule' => ['calendar-days', 'My schedule'], 'profile' => ['user', 'My profile']] as $section => [$icon, $label])
                        <flux:sidebar.item :icon="$icon" :href="route('employee.'.$section)" :current="request()->routeIs('employee.'.$section)" :aria-current="request()->routeIs('employee.'.$section) ? 'page' : null" wire:navigate>{{ $label }}</flux:sidebar.item>
                    @endforeach
                @endif
            </flux:sidebar.nav>
            <flux:spacer />
            <flux:sidebar.nav aria-label="Account navigation">
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>Settings</flux:sidebar.item>
            </flux:sidebar.nav>
            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-800"><x-desktop-user-menu /></div>
        </flux:sidebar>
        <flux:header class="min-h-16 border-b border-zinc-200 bg-white px-4! sm:px-8! dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" aria-label="Open navigation" />
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Workspace <span aria-hidden="true" class="mx-2 text-zinc-300">/</span> <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $title ?? __('Attendify') }}</span></div>
            <flux:spacer />
            <flux:button x-data x-on:click="$flux.appearance = $flux.dark ? 'light' : 'dark'" variant="ghost" size="sm" icon="sun" aria-label="Toggle light or dark appearance" />
            <span class="hidden border-l border-zinc-200 pl-4 text-xs text-zinc-500 sm:block dark:border-zinc-700 dark:text-zinc-400">{{ auth()->user()->role === 'admin' ? __('Admin') : __('Employee') }}</span>
        </flux:header>
        {{ $slot }}
        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
