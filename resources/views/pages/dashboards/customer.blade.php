<x-layouts.app title="My dashboard">
<div x-data="customerDashboard(@js($data))" x-init="start()">
    <x-page-header title="Dashboard" subtitle="Welcome to ParkFlow, {{ auth()->user()->name }}. Manage your parking, vehicles and account in one place.">
        <a href="{{ route('my.vehicles') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/>Add a vehicle</a>
    </x-page-header>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Registered vehicles" value="d.vehicles" icon="car" href="{{ route('my.vehicles') }}"/>
        <x-kpi label="Currently parked" value="d.active_sessions" icon="logo" href="{{ route('my.active') }}"/>
        <x-kpi label="Credit balance" value="$fmt.money(d.credit_balance_minor)" icon="card" href="{{ route('my.credits') }}"/>
        <x-kpi label="Unread notifications" value="d.unread_notifications" icon="bell" href="{{ route('notifications') }}"/>
    </div>
    <section class="mt-6" aria-labelledby="quick-h">
        <h2 id="quick-h" class="card-title mb-3">Quick actions</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('my.vehicles') }}" class="card flex items-center gap-3 p-4 font-semibold text-ink hover:border-brand-soft hover:bg-brand-faint"><x-icon name="car" class="h-5 w-5 text-brand-strong"/>View my vehicles<x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>
            <a href="{{ route('my.active') }}" class="card flex items-center gap-3 p-4 font-semibold text-ink hover:border-brand-soft hover:bg-brand-faint"><x-icon name="logo" class="h-5 w-5 text-brand-strong"/>Active parking<x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>
            <a href="{{ route('my.parking-info') }}" class="card flex items-center gap-3 p-4 font-semibold text-ink hover:border-brand-soft hover:bg-brand-faint"><x-icon name="grid" class="h-5 w-5 text-brand-strong"/>Parking info & prices<x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>
            <a href="{{ route('my.support') }}" class="card flex items-center gap-3 p-4 font-semibold text-ink hover:border-brand-soft hover:bg-brand-faint"><x-icon name="chat" class="h-5 w-5 text-brand-strong"/>Contact support<x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>
        </div>
    </section>
    <section class="mt-6" aria-labelledby="act-h">
        <div class="mb-3 flex items-center justify-between"><h2 id="act-h" class="card-title">Current parking</h2><a href="{{ route('my.active') }}" class="text-sm font-semibold">View active parking →</a></div>
        <template x-if="active === null"><div class="card card-pad"><div class="skeleton h-5 w-1/2"></div></div></template>
        <template x-if="active && !active.length"><div class="card card-pad text-sm text-ink-muted">None of your vehicles are parked right now. Sessions appear here when staff record your Check-In.</div></template>
        <div class="grid gap-3 md:grid-cols-2">
            <template x-for="s in active || []" :key="s.uuid">@include('pages.my._session-card')</template>
        </div>
    </section>
</div>
</x-layouts.app>
