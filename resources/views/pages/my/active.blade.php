<x-layouts.app title="Active parking">
<div x-data="dataTable({ url: '/customer/sessions', filters: { status: 'active,exit_authorized' }, syncUrl: false })" x-init="setInterval(() => !loading && load(), 60000)">
    <x-page-header title="Active parking" subtitle="Vehicles currently parked under your account. Remaining time updates every minute from the server."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="loading && !rows.length"><div class="card card-pad"><div class="skeleton h-6 w-1/2"></div></div></template>
    <template x-if="empty"><div class="card card-pad text-ink-muted">None of your vehicles are parked right now. When staff record your Check-In (with a mobile number or vehicle linked to this account), it appears here.</div></template>
    <div class="grid gap-3 md:grid-cols-2"><template x-for="s in rows" :key="s.uuid">@include('pages.my._session-card')</template></div>
    <p class="mt-4 text-xs text-ink-muted">Online payment from this portal is not available. Pay at the exit when staff check you out.</p>
</div>
</x-layouts.app>
