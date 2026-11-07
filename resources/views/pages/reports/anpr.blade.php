<x-layouts.app title="ANPR & cameras" wide>
<div x-data="{ ov: null, health: null, hours: '24', error: null,
    async load() { try { this.ov = (await ParkFlow.api.get('/reports/anpr/overview', { hours: this.hours })).data; this.health = (await ParkFlow.api.get('/reports/anpr/camera-health')).data; this.error = null; } catch (e) { this.error = e.message } },
    init() { this.load() } }">
    <x-page-header title="ANPR & cameras" subtitle="Counts of readings and how staff handled them. This is not a measured recognition accuracy.">
        <form class="flex items-end gap-2" @submit.prevent="load()"><div><label class="label" for="an-h">Period</label><select id="an-h" class="input" x-model="hours" @change="load()"><option value="24">Last 24 hours</option><option value="168">Last 7 days</option><option value="720">Last 30 days</option></select></div></form>
    </x-page-header>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="ov"><div class="space-y-4">
        <div class="alert-info text-sm" x-text="ov.note"></div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
            <x-kpi label="Automatic readings" value="ov.automatic_events" icon="camera"/><x-kpi label="Read without help" value="$fmt.percent(ov.recognition_rate_percent)" icon="check"/><x-kpi label="Awaiting review" value="ov.pending_reviews" icon="clock"/>
            <x-kpi label="Corrections" value="ov.corrections" icon="refresh"/><x-kpi label="Manual identifications" value="ov.manual_identifications" icon="user"/><x-kpi label="Avg. processing" value="`${ov.avg_processing_ms} ms`" icon="pulse"/>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card card-pad"><h2 class="card-title">Readings by status</h2><ul class="mt-3 space-y-1 text-sm"><template x-for="[k, v] in Object.entries(ov.events_by_status || {})" :key="k"><li class="flex justify-between"><x-state kind="recognition" value="k"/><span class="font-semibold" x-text="v"></span></li></template></ul></section>
            <section class="card card-pad"><h2 class="card-title">Engine & cameras</h2>
                <p class="mt-2 text-sm">Engine: <span :class="health?.engine?.available ? 'chip-success' : 'chip-danger'" x-text="health?.engine?.available ? `Available (${health.engine.driver}, ${health.engine.latency_ms ?? '—'} ms)` : `Unavailable (${health?.engine?.driver})`"></span></p>
                <ul class="mt-3 divide-y divide-line text-sm"><template x-for="c in health?.cameras || []" :key="c.id ?? c.code"><li class="flex items-center justify-between gap-2 py-2"><span><strong x-text="c.code"></strong> <span class="text-ink-muted" x-text="c.name"></span><span class="block text-xs text-ink-muted" x-text="`Heartbeat ${$fmt.dateTime(c.last_heartbeat_at, 'never')}`"></span></span><x-state kind="camera" value="c.is_enabled === false ? 'disabled' : c.status"/></li></template></ul></section>
        </div>
        <section class="card"><h2 class="card-title px-4 pt-4">Recent camera health changes</h2><div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>When</th><th>Component</th><th>Status</th><th>Message</th></tr></thead><tbody>
            <template x-for="(l, i) in health?.recent_logs || []" :key="i"><tr><td x-text="$fmt.dateTime(l.created_at)"></td><td x-text="$fmt.titleCase(l.component)"></td><td x-text="$fmt.titleCase(l.status)"></td><td class="text-sm" x-text="l.message || '—'"></td></tr></template>
            <tr x-show="!(health?.recent_logs || []).length"><td colspan="4" class="text-ink-muted">No changes recorded.</td></tr></tbody></table></div></section>
        <section x-data="dataTable({ url: '/reports/anpr/recognition-history', filters: { from: ParkFlow.fmt.todayLocal(-6), to: ParkFlow.fmt.todayLocal(), recognition_status: '' }, perPage: 50, syncUrl: false })">
            <div class="mb-2 flex flex-wrap items-end justify-between gap-2"><h2 class="card-title">Recognition history</h2>@include('pages.reports._range')</div>
            @include('pages.ops._anpr-table', ['canAct' => false])</section>
    </div></template>
</div>
</x-layouts.app>
