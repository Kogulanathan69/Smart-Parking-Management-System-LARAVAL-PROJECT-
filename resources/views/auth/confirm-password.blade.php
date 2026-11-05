<x-layouts.guest title="Confirm your password" subtitle="For your security, confirm your password to continue.">
    <form method="POST" action="{{ url('/user/confirm-password') }}" class="space-y-4">
        @csrf
        <x-field name="password" label="Password" type="password" required autocomplete="current-password" autofocus/>
        <button type="submit" class="btn-primary w-full">Confirm</button>
    </form>
</x-layouts.guest>
