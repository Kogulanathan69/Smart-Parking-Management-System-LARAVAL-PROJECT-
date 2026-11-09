<x-layouts.guest title="Create a customer account" subtitle="See your parking sessions, receipts and vehicles. Staff accounts cannot be created here.">
    <form method="POST" action="{{ route('register') }}" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <x-field name="name" label="Full name" required autocomplete="name"/>
        <x-field name="email" label="Email" type="email" required autocomplete="email"/>
        <x-field name="phone" label="Mobile number" type="tel" autocomplete="tel" hint="Optional. Sri Lankan format, e.g. 077 123 4567. Used to link sessions recorded at the gate."/>
        <x-field name="password" label="Password" type="password" required autocomplete="new-password" hint="At least 8 characters."/>
        <x-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password"/>
        <button type="submit" class="btn-primary w-full" :disabled="busy">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-ink-muted">Already registered? <a href="{{ route('login') }}" class="font-semibold">Sign in</a></p>
</x-layouts.guest>
