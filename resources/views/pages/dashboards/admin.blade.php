<x-layouts.app title="Admin dashboard" wide>
<div x-data="opsDashboard(@js($data))" x-init="start()">
    <x-page-header title="Operations overview" subtitle="Live counts from the server. Refreshes every 60 seconds.">
        <a href="{{ route('ops.check-in') }}" class="btn-secondary"><x-icon name="arrow-in" class="h-4 w-4"/>Check-In</a>
        <a href="{{ route('ops.check-out') }}" class="btn-secondary"><x-icon name="arrow-out" class="h-4 w-4"/>Check-Out</a>
        <button type="button" class="btn-secondary" @click="refresh()" :disabled="busy"><x-icon name="refresh" class="h-4 w-4"/>Refresh</button>
    </x-page-header>
    @include('pages.dashboards._ops-kpis')
    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2" x-data="{ get occ() { return d.occupancy }, pct(z) { return z.total ? Math.round((z.occupied + z.reserved) / z.total * 100) : 0 } }">@include('pages.dashboards._occupancy')</div>
        <div class="space-y-4">@include('pages.dashboards._anpr-card')
            <section class="card card-pad"><h2 class="card-title">Management shortcuts</h2>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <a class="btn-secondary btn-sm" href="{{ route('manage.zones') }}">Zones & slots</a><a class="btn-secondary btn-sm" href="{{ route('manage.packages') }}">Packages & rates</a>
                    <a class="btn-secondary btn-sm" href="{{ route('manage.shifts') }}">Roster</a><a class="btn-secondary btn-sm" href="{{ route('manage.refunds') }}">Refunds</a>
                    <a class="btn-secondary btn-sm" href="{{ route('manage.users') }}">Users</a><a class="btn-secondary btn-sm" href="{{ route('reports.financial') }}">Financial report</a>
                </div></section>
        </div>
    </div>
</div>
</x-layouts.app>
