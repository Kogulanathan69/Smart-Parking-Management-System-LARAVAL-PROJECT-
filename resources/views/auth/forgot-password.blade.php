<x-layouts.guest title="Reset your password" subtitle="Enter your account email. If it exists, we will send a reset link.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <x-field name="email" label="Email" type="email" required autocomplete="username" autofocus/>
        <button type="submit" class="btn-primary w-full" :disabled="busy">Send reset link</button>
    </form>
    <p class="mt-4 text-xs text-ink-muted">This installation must have an email (SMTP) service configured for the link to arrive. If it does not, ask the parking office to help.</p>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}">Back to sign in</a></p>
</x-layouts.guest>
