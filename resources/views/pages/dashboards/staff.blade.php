<x-layouts.app title="Staff dashboard">
<div x-data="staffDashboard(@js($data))" x-init="start()">
    <x-page-header title="Good {{ now(config('parking.business_timezone'))->hour < 12 ? 'morning' : (now(config('parking.business_timezone'))->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}" subtitle="Gate operations at a glance. Plate readings always need your verification.">
        <button type="button" class="btn-secondary" @click="refresh()" :disabled="busy"><x-icon name="refresh" class="h-4 w-4"/>Refresh</button>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2">
        <a href="{{ route('ops.check-in') }}" class="card group flex items-center gap-4 p-5 hover:border-brand-strong">
            <span class="grid h-14 w-14 place-items-center rounded-xl bg-brand-faint text-brand-strong group-hover:bg-brand-soft"><x-icon name="arrow-in" class="h-7 w-7"/></span>
            <span><span class="block text-lg font-semibold text-ink">Check-In</span><span class="block text-sm text-ink-muted">Capture or upload the plate, verify, assign a slot</span></span>
        </a>
        <a href="{{ route('ops.check-out') }}" class="card group flex items-center gap-4 p-5 hover:border-brand-strong">
            <span class="grid h-14 w-14 place-items-center rounded-xl bg-info text-info-ink"><x-icon name="arrow-out" class="h-7 w-7"/></span>
            <span><span class="block text-lg font-semibold text-ink">Check-Out</span><span class="block text-sm text-ink-muted">Match the session, take payment, release the gate</span></span>
        </a>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi label="Vehicles parked" value="d.active_sessions" icon="car" href="{{ route('ops.sessions') }}"/>
        <x-kpi label="Free slots" value="free" icon="grid" hint="Across all active zones"/>
        <x-kpi label="ANPR review required" value="pendingReviews ?? '…'" icon="camera" href="{{ route('ops.anpr.queue') }}"/>
        <x-kpi label="Safety checks due" value="d.due_inspections" icon="shield" hint="Within the next hour" href="{{ route('ops.inspections') }}"/>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2" x-data="{ get occ() { return d.occupancy }, pct(z) { return z.total ? Math.round((z.occupied + z.reserved) / z.total * 100) : 0 } }">
            @include('pages.dashboards._occupancy')
        </div>
        <div class="space-y-4">
            <section class="card card-pad" aria-labelledby="shift-h">
                <h2 id="shift-h" class="card-title">My shift today</h2>
                <template x-if="!d.my_shifts_today.length"><p class="mt-2 text-sm text-ink-muted">You have no shift scheduled for today.</p></template>
                <ul class="mt-2 space-y-2">
                    <template x-for="s in d.my_shifts_today" :key="s.id"><li class="flex items-center justify-between text-sm"><span class="font-medium capitalize" x-text="s.shift"></span><x-state kind="shift" value="s.status"/></li></template>
                </ul>
                <a href="{{ route('ops.my-shifts') }}" class="mt-3 inline-block text-sm font-semibold">Start or end a shift →</a>
            </section>
            <section class="card card-pad" aria-labelledby="exp-h">
                <div class="flex items-center justify-between"><h2 id="exp-h" class="card-title">Expiring within 60 min</h2><a href="{{ route('ops.expiring') }}" class="text-xs font-semibold">All</a></div>
                <template x-if="expiring === null"><div class="mt-3 space-y-2"><div class="skeleton h-4"></div><div class="skeleton h-4 w-2/3"></div></div></template>
                <template x-if="expiring && !expiring.length"><p class="mt-2 text-sm text-ink-muted">No sessions are about to expire.</p></template>
                <ul class="mt-2 divide-y divide-line">
                    <template x-for="s in (expiring || []).slice(0, 6)" :key="s.uuid">
                        <li class="py-2"><a :href="`/ops/sessions/${s.uuid}`" class="flex items-center justify-between gap-2 text-sm hover:no-underline">
                            <span class="plate text-xs" x-text="s.vehicle?.plate_number || s.vehicle?.identifier"></span>
                            <x-state kind="time" value="s.time.status"/></a></li>
                    </template>
                </ul>
            </section>
        </div>
    </div>
</div>
</x-layouts.app>
