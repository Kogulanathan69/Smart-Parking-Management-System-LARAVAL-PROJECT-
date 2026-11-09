<x-layouts.app title="My account">
<x-page-header title="My account" subtitle="Your profile, password and sign-in security."/>
<div class="grid gap-4 lg:grid-cols-2">
    <section class="card card-pad" aria-labelledby="prof-h" x-data="form({ name: $store.app.user.name, email: $store.app.user.email, phone: $store.app.user.phone || '' })">
        <h2 id="prof-h" class="card-title">Profile</h2>
        <form class="mt-4 space-y-4" @submit.prevent="submit(async (d) => { await ParkFlow.api.put('web:/user/profile-information', d); ParkFlow.toast('Profile saved.'); Object.assign($store.app.user, d); })">
            <x-xfield label="Full name" model="data.name" name="name" form="$data" required autocomplete="name"/>
            <x-xfield label="Email" model="data.email" name="email" type="email" form="$data" required autocomplete="email"/>
            <x-xfield label="Mobile number" model="data.phone" name="phone" type="tel" form="$data" autocomplete="tel" hint="Optional, e.g. 077 123 4567."/>
            <div class="flex items-center gap-3"><button class="btn-primary" :disabled="busy">Save profile</button>
                <span class="text-sm text-ink-muted">Role: <strong x-text="$fmt.roleLabel($store.app.user.role)"></strong> (set by the parking office)</span></div>
        </form>
    </section>

    <section class="card card-pad" aria-labelledby="pw-h" x-data="form({ current_password: '', password: '', password_confirmation: '' }, null, { reset: true })">
        <h2 id="pw-h" class="card-title">Change password</h2>
        <form class="mt-4 space-y-4" @submit.prevent="submit(async (d) => { await ParkFlow.api.put('web:/user/password', d); ParkFlow.toast('Password changed.'); })">
            <x-xfield label="Current password" model="data.current_password" name="current_password" type="password" form="$data" required autocomplete="current-password"/>
            <x-xfield label="New password" model="data.password" name="password" type="password" form="$data" required autocomplete="new-password" hint="At least 8 characters."/>
            <x-xfield label="Confirm new password" model="data.password_confirmation" name="password_confirmation" type="password" form="$data" required autocomplete="new-password"/>
            <button class="btn-primary" :disabled="busy">Change password</button>
        </form>
    </section>

    <section class="card card-pad lg:col-span-2" aria-labelledby="tfa-h" x-data="twoFactor()" x-init="init()">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="tfa-h" class="card-title">Two-step verification</h2>
            <span :class="enabled ? 'chip-success' : 'chip-neutral'" x-text="enabled ? 'On' : 'Off'"></span>
        </div>
        <p class="mt-1 text-sm text-ink-muted">Adds a 6-digit code from an authenticator app (for example Google Authenticator or Microsoft Authenticator) when you sign in. Recommended for admin and staff accounts.</p>

        <template x-if="needsPassword">
            <form class="mt-4 flex max-w-md flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="confirmPassword()">
                <div class="flex-1"><label class="label" for="tfa-pw">Confirm your password to continue</label><input id="tfa-pw" type="password" class="input" x-model="password" autocomplete="current-password" required></div>
                <button class="btn-primary" :disabled="busy">Confirm</button>
            </form>
        </template>

        <div class="mt-4" x-show="!needsPassword">
            <template x-if="!enabled && !setup"><button type="button" class="btn-primary" @click="enable()" :disabled="busy">Turn on two-step verification</button></template>
            <template x-if="setup">
                <div class="grid gap-4 md:grid-cols-[auto_1fr]">
                    <div class="rounded-lg border border-line bg-white p-3" x-html="qr" aria-label="QR code to scan with your authenticator app"></div>
                    <form class="space-y-3" @submit.prevent="confirmCode()">
                        <p class="text-sm">1. Scan the QR code with your authenticator app, or enter this key: <code class="rounded bg-neutral px-1.5 py-0.5 font-mono text-xs" x-text="secret"></code></p>
                        <div class="max-w-xs"><label class="label" for="tfa-code">2. Enter the 6-digit code</label><input id="tfa-code" class="input font-mono tracking-widest" inputmode="numeric" autocomplete="one-time-code" maxlength="6" x-model="code" required>
                            <p class="field-error" x-show="error" x-text="error"></p></div>
                        <div class="flex gap-2"><button class="btn-primary" :disabled="busy">Confirm and turn on</button><button type="button" class="btn-secondary" @click="disable()" :disabled="busy">Cancel</button></div>
                    </form>
                </div>
            </template>
            <template x-if="enabled">
                <div class="space-y-3">
                    <div class="flex flex-wrap gap-2"><button type="button" class="btn-secondary" @click="showCodes()" :disabled="busy">Show recovery codes</button>
                        <button type="button" class="btn-secondary" @click="regenerate()" :disabled="busy">Generate new recovery codes</button>
                        <button type="button" class="btn-danger" @click="disable(true)" :disabled="busy">Turn off</button></div>
                    <template x-if="codes.length"><div class="alert-warning"><p class="font-semibold">Store these codes somewhere safe. Each works once if you lose your phone.</p>
                        <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-sm sm:grid-cols-4"><template x-for="c in codes" :key="c"><li x-text="c"></li></template></ul></div></template>
                </div>
            </template>
        </div>
    </section>
</div>
</x-layouts.app>
