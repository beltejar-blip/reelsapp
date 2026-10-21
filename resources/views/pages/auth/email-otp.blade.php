<x-layouts::auth :title="__('Verify your email')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Check your email')"
            :description="__('Enter the six-digit code sent to :email. The code expires in 10 minutes.', ['email' => $user->email])"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        @error('email')
            <p class="text-center text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('email-otp.verify') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="code"
                :label="__('One-time code')"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]{6}"
                maxlength="6"
                required
                autofocus
                :placeholder="__('000000')"
            />

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Verify and log in') }}
            </flux:button>
        </form>

        <form method="POST" action="{{ route('email-otp.resend') }}" class="flex flex-col gap-3">
            @csrf
            <flux:button type="submit" variant="ghost" class="w-full">
                {{ __('Send a new code') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
