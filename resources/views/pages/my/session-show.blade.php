<x-layouts.app title="Parking session" :crumbs="['Parking history' => route('my.history')]">
<div x-data="{ s: null, b: null, loading: true, error: null, notFound: false,
    async reload() { try { const [s, b] = await Promise.all([ParkFlow.api.get('/customer/sessions/{{ $uuid }}'), ParkFlow.api.get('/customer/sessions/{{ $uuid }}/bill')]); this.s = s.data; this.b = b.data; } catch (e) { this.error = e.message; this.notFound = [403, 404].includes(e.status); } finally { this.loading = false } },
    init() { this.reload() } }">
    @include('pages.ops._record-states', ['var' => 's'])
    <template x-if="s"><div class="grid gap-4 lg:grid-cols-5">
        <section class="card card-pad lg:col-span-2"><div class="flex items-start justify-between gap-2"><div><p class="text-sm text-ink-muted">Session</p><p class="text-lg font-semibold" x-text="s.reference"></p></div><x-state kind="session" value="s.status"/></div>
            <p class="mt-2"><span class="plate" x-text="s.vehicle?.plate_number || s.vehicle?.identifier"></span></p>
            <dl class="dl mt-4"><dt>Zone / slot</dt><dd x-text="`${s.zone?.code} / ${s.slot?.code}`"></dd><dt>Package</dt><dd x-text="s.package?.name"></dd><dt>Checked in</dt><dd x-text="$fmt.dateTime(s.entry_at)"></dd><dt>Checked out</dt><dd x-text="$fmt.dateTime(s.exit_at, 'Still parked')"></dd>
                <dt>Time</dt><dd><x-state kind="time" value="s.time?.status"/></dd></dl>
            <div class="mt-4 flex flex-wrap gap-2"><a class="btn-secondary btn-sm" x-show="s.receipt_number" :href="`/my/receipts/${s.receipt_number}`">View receipt</a><a class="btn-secondary btn-sm" :href="`/my/support?session=${s.uuid}`">Ask about this session</a></div></section>
        <section class="card card-pad lg:col-span-3"><h2 class="card-title" x-text="['active','exit_authorized'].includes(s.status) ? 'Bill so far (estimate as of now)' : 'Final bill'"></h2><div class="mt-3">@include('pages.partials._bill')</div>
            <p class="mt-2 text-xs text-ink-muted" x-show="['active','exit_authorized'].includes(s.status)">The amount due is finalised by the server at Check-Out.</p></section>
    </div></template>
</div>
</x-layouts.app>
