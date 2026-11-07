<x-layouts.app title="Active parking">
<div x-data="dataTable({ url: '/customer/sessions', filters: { status: 'active,exit_authorized' }, syncUrl: false })" x-init="setInterval(() => !loading && load(), 60000)">
    <x-page-header title="Active parking" subtitle="Vehicles currently parked under your account. Remaining time updates every minute from the server."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="loading && !rows.length"><div class="card card-pad"><div class="skeleton h-6 w-1/2"></div></div></template>
    <template x-if="empty"><div class="card card-pad text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-faint text-brand-strong"><x-icon name="car" class="h-7 w-7"/></span><h2 class="mt-3 card-title">No active parking</h2><p class="mx-auto mt-1 max-w-xl text-sm text-ink-muted">When staff record your Check-In using a vehicle or mobile number linked to your account, the session appears here.</p><a href="{{ route('my.vehicles') }}" class="btn-secondary mt-4">View my vehicles</a></div></template>
    <div class="grid gap-3 md:grid-cols-2"><template x-for="s in rows" :key="s.uuid">@include('pages.my._session-card')</template></div>
    <p class="mt-4 text-xs text-ink-muted">Online payment from this portal is not available. Pay at the exit when staff check you out.</p>
</div>
</x-layouts.app>
