<x-layouts.guest title="Sign in" subtitle="Use the account issued by your parking office, or your customer account.">
    @if (request('expired'))<div class="alert-warning mb-4" role="status">Your session ended. Please sign in again.</div>@endif
    <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
        @csrf
        <x-field name="email" label="Email" type="email" required autocomplete="username" autofocus/>
        <x-field name="password" label="Password" type="password" required autocomplete="current-password"/>
        <div class="flex items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" class="check" @checked(old('remember'))> Keep me signed in</label>
            @if (Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password?</a>@endif
        </div>
        <button type="submit" class="btn-primary w-full" :disabled="busy"><span x-show="!busy">Sign in</span><span x-show="busy" x-cloak>Signing in…</span></button>
    </form>
    @if (Route::has('register'))
        <p class="mt-6 border-t border-line pt-5 text-center text-sm text-ink-muted">Parking customer? <a href="{{ route('register') }}" class="font-semibold">Create a customer account</a><br>
        <span class="text-xs">Staff, admin and auditor accounts are created by the parking office.</span></p>
    @endif
</x-layouts.guest>
