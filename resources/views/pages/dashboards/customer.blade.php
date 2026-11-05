<x-layouts.app title="My dashboard">
<div x-data="customerDashboard(@js($data))" x-init="start()">
    <x-page-header title="Welcome, {{ auth()->user()->name }}" subtitle="Your parking at a glance. Times are Sri Lanka time.">
        <a href="{{ route('my.vehicles') }}" class="btn-secondary"><x-icon name="plus" class="h-4 w-4"/>Add a vehicle</a>
    </x-page-header>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Parked now" value="d.active_sessions" href="{{ route('my.active') }}"/>
        <x-kpi label="My vehicles" value="d.vehicles" href="{{ route('my.vehicles') }}"/>
        <x-kpi label="Credit balance" value="$fmt.money(d.credit_balance_minor)" href="{{ route('my.credits') }}"/>
        <x-kpi label="Unread notifications" value="d.unread_notifications" href="{{ route('notifications') }}"/>
    </div>
    <section class="mt-4" aria-labelledby="act-h">
        <h2 id="act-h" class="section-title mb-2">Active parking</h2>
        <template x-if="active === null"><div class="card card-pad"><div class="skeleton h-5 w-1/2"></div></div></template>
        <template x-if="active && !active.length"><div class="card card-pad text-sm text-ink-muted">None of your vehicles are parked right now. Sessions appear here when staff record your Check-In.</div></template>
        <div class="grid gap-3 md:grid-cols-2">
            <template x-for="s in active || []" :key="s.uuid">@include('pages.my._session-card')</template>
        </div>
    </section>
</div>
</x-layouts.app>
