<x-layouts.guest title="Welcome Back" subtitle="Sign in to your ParkFlow account to manage parking operations, vehicles, and more.">
    @if (request('expired'))<div class="alert-warning mb-4" role="status">Your session ended. Please sign in again.</div>@endif
    <form method="POST" action="{{ route('login') }}" class="space-y-4 2xl:space-y-5" x-data="{ busy: false, showPassword: false }" @submit="busy = true">
        @csrf
        <x-field name="email" label="Email" type="email" placeholder="Enter your email address" required autocomplete="username" autofocus/>
        <div>
            <label for="f-password" class="label">Password<span class="text-danger-ink" aria-hidden="true"> *</span></label>
            <div class="relative"><input id="f-password" name="password" :type="showPassword ? 'text' : 'password'" class="input pr-20" placeholder="Enter your password" required aria-required="true" autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="f-password-error" @enderror>
                <button type="button" class="absolute inset-y-0 right-1 my-1 rounded-lg px-3 text-xs font-semibold text-brand-strong hover:bg-brand-faint" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'" x-text="showPassword ? 'Hide' : 'Show'"></button></div>
            @error('password')<p id="f-password-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" class="check" @checked(old('remember'))> Keep me signed in</label>
            @if (Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password?</a>@endif
        </div>
        <button type="submit" class="btn-primary btn-lg w-full shadow-[0_10px_24px_rgb(8_127_70/0.22)]" :disabled="busy"><span x-show="!busy" class="flex items-center justify-center gap-2"><x-icon name="arrow-in" class="h-5 w-5"/>Sign In</span><span x-show="busy" x-cloak class="flex items-center gap-2"><span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>Signing in…</span></button>
    </form>
    @if (Route::has('register'))
        <div class="relative my-4 flex items-center 2xl:my-6"><span class="h-px flex-1 bg-line"></span><span class="mx-3 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">or</span><span class="h-px flex-1 bg-line"></span></div>
        <a href="{{ route('register') }}" class="group flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3 text-left text-[#102a23] hover:border-emerald-300 hover:bg-emerald-50 hover:text-[#102a23] 2xl:p-4">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-[#087f46] shadow-sm"><x-icon name="user" class="h-6 w-6"/></span>
            <span class="min-w-0"><strong class="block text-sm">Parking customer?</strong><span class="block text-xs leading-5 text-slate-600">Create an account to manage vehicles, view history and receipts.</span></span>
            <x-icon name="chevron-right" class="ml-auto h-5 w-5 shrink-0 text-[#087f46] transition-transform group-hover:translate-x-1"/>
        </a>
        <p class="mt-3 text-center text-[11px] text-slate-400 2xl:mt-4">Staff, admin and auditor accounts are created by the parking office.</p>
    @endif
</x-layouts.guest>
