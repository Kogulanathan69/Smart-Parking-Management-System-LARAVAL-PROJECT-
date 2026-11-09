<x-layouts.guest title="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-field name="email" label="Email" type="email" required autocomplete="username" :value="$request->email"/>
        <x-field name="password" label="New password" type="password" required autocomplete="new-password"/>
        <x-field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password"/>
        <button type="submit" class="btn-primary w-full" :disabled="busy">Save new password</button>
    </form>
</x-layouts.guest>
