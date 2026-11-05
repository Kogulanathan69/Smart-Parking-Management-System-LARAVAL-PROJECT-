<x-layouts.guest title="Two-step verification">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <p class="text-sm text-ink-muted" x-show="!recovery">Enter the 6-digit code from your authenticator app.</p>
        <p class="text-sm text-ink-muted" x-show="recovery" x-cloak>Enter one of your emergency recovery codes.</p>
        <form method="POST" action="{{ url('/two-factor-challenge') }}" class="mt-4 space-y-4">
            @csrf
            <div x-show="!recovery"><x-field name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" x-bind:disabled="recovery"/></div>
            <div x-show="recovery" x-cloak><x-field name="recovery_code" label="Recovery code" autocomplete="off" x-bind:disabled="!recovery"/></div>
            <button type="submit" class="btn-primary w-full">Verify</button>
        </form>
        <button type="button" class="btn-ghost mt-3 w-full" @click="recovery = !recovery" x-text="recovery ? 'Use an authentication code' : 'Use a recovery code'"></button>
    </div>
</x-layouts.guest>
