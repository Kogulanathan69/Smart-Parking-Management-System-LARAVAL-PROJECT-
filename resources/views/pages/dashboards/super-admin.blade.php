<x-layouts.app title="Super Admin dashboard" wide>
<div x-data="opsDashboard(@js($data))" x-init="start()">
    <x-page-header title="Executive overview" subtitle="Whole-site status. All figures come from the server.">
        <a href="{{ route('manage.system') }}" class="btn-secondary"><x-icon name="pulse" class="h-4 w-4"/>System status</a>
        <button type="button" class="btn-secondary" @click="refresh()" :disabled="busy"><x-icon name="refresh" class="h-4 w-4"/>Refresh</button>
    </x-page-header>
    @include('pages.dashboards._ops-kpis')
    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <div x-data="{ get fin() { return d.financial_7_days }, get maxDay() { return Math.max(0, ...d.financial_7_days.by_day.map(x => x.revenue_minor ?? x.amount_minor ?? 0)) } }">@include('pages.dashboards._financial')</div>
            <div x-data="{ get occ() { return d.occupancy }, pct(z) { return z.total ? Math.round((z.occupied + z.reserved) / z.total * 100) : 0 } }">@include('pages.dashboards._occupancy')</div>
        </div>
        <div class="space-y-4">
            @include('pages.dashboards._anpr-card')
            <section class="card card-pad" aria-labelledby="ub-h"><div class="flex items-center justify-between"><h2 id="ub-h" class="card-title">Accounts by role</h2><a href="{{ route('manage.users') }}" class="text-xs font-semibold">Manage</a></div>
                <ul class="mt-3 space-y-1.5 text-sm"><template x-for="r in d.users_by_role" :key="r.role + r.status"><li class="flex justify-between"><span x-text="$fmt.roleLabel(r.role) + (r.status !== 'active' ? ` (${$fmt.stateLabel('user', r.status).toLowerCase()})` : '')"></span><span class="font-semibold tabular-nums" x-text="r.total"></span></li></template></ul></section>
        </div>
    </div>
</div>
</x-layouts.app>
