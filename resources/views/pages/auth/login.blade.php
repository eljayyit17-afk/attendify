
<x-layouts::auth :title="__('Attendify | Login')">
    <div class="flex flex-col gap-6">

        <!-- Login Header -->
        <x-auth-header
            :title="__('Welcome to Attendify')"
            :description="__('Sign in to access your attendance management system.')"
        />

        <!-- Session Status -->
        <x-auth-session-status
            class="text-center"
            :status="session('status')"
        />

        <!-- Passkey Verification -->
        <x-passkey-verify />

        <!-- Login Form -->
        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="flex flex-col gap-6"
        >
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email Address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="name@company.com"
            />

            <!-- Password -->
            <div class="relative">

                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Enter your password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link
                        class="absolute top-0 text-sm end-0"
                        :href="route('password.request')"
                        wire:navigate
                    >
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif

            </div>

            <!-- Remember Me -->
            <flux:checkbox
                name="remember"
                :label="__('Remember me')"
                :checked="old('remember')"
            />

            <!-- Login Button -->
            <div class="flex items-center justify-end">

                <flux:button
                    variant="primary"
                    type="submit"
                    class="w-full"
                    data-test="login-button"
                >
                    {{ __('Log in to Attendify') }}
                </flux:button>

            </div>
        </form>

        <!-- Account Information -->
        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <p>
                Employee accounts are managed by your
                system administrator.
            </p>
        </div>

    </div>
</x-layouts::auth>
