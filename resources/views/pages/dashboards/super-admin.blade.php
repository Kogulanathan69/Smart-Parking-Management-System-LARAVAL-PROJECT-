<x-layouts.app title="Parking Business Overview" wide>
<div x-data="opsDashboard(@js($data))" x-init="start()" class="dashboard-shell">
    <x-page-header title="Parking Business Overview" subtitle="Monitor parking activity, revenue, availability and system health.">
        <span class="date-context"><x-icon name="calendar" class="h-4 w-4"/><span x-text="$fmt.date(d.business_date)"></span><span class="hidden lg:inline">· Sri Lanka time</span></span>
        <button type="button" class="btn-secondary" @click="refresh()" :disabled="busy"><x-icon name="refresh" class="h-4 w-4"/><span x-text="busy ? 'Refreshing…' : 'Refresh'"></span></button>
        <a href="{{ route('reports.overview') }}" class="btn-primary"><x-icon name="chart" class="h-4 w-4"/>View reports</a>
    </x-page-header>

    <section aria-labelledby="primary-metrics">
        <h2 id="primary-metrics" class="sr-only">Business summary</h2>
        <div class="executive-kpis">
            <a href="{{ route('reports.financial') }}" class="executive-kpi"><span class="executive-kpi-icon"><x-icon name="cash"/></span><span class="kpi-label">Today's revenue</span><strong class="executive-kpi-value whitespace-nowrap" x-text="$fmt.money(d.revenue_today_minor)"></strong><span class="executive-kpi-foot">Settled payments · Sri Lanka day</span></a>
            <a href="{{ route('ops.availability') }}" class="executive-kpi"><span class="executive-kpi-icon"><x-icon name="grid"/></span><span class="kpi-label">Parking occupancy</span><strong class="executive-kpi-value"><span x-text="occupiedSpaces"></span><span class="text-lg font-semibold text-ink-muted"> / </span><span x-text="totalSpaces"></span></strong><span class="executive-kpi-foot" x-text="`${occupancyPercent}% of active capacity in use`"></span></a>
            <a href="{{ route('ops.sessions') }}" class="executive-kpi"><span class="executive-kpi-icon"><x-icon name="car"/></span><span class="kpi-label">Today's vehicle activity</span><strong class="executive-kpi-value" x-text="d.entries_today + d.exits_today"></strong><span class="executive-kpi-foot" x-text="`${d.entries_today} check-ins · ${d.exits_today} check-outs`"></span></a>
            <a href="#needs-attention" class="executive-kpi" :class="attentionTotal ? 'executive-kpi-warning' : ''"><span class="executive-kpi-icon"><x-icon name="alert"/></span><span class="kpi-label">Attention required</span><strong class="executive-kpi-value" x-text="attentionTotal"></strong><span class="executive-kpi-foot" x-text="attentionTotal ? 'Open operational actions' : 'No current business alerts'"></span></a>
        </div>
    </section>

    <section class="secondary-metrics" aria-label="Current operational totals">
        <div><span>Available spaces</span><strong x-text="availableSpaces"></strong></div><div><span>Active sessions</span><strong x-text="d.active_sessions"></strong></div><div><span>Overdue vehicles</span><strong x-text="d.overdue_sessions"></strong></div><div><span>Pending refunds</span><strong x-text="d.pending_refunds"></strong></div>
    </section>

    <section class="card card-pad mt-4" aria-labelledby="quick-actions">
        <h2 id="quick-actions" class="card-title">Quick Actions</h2><p class="mt-1 text-sm text-ink-muted">Common tasks for running the parking operation.</p>
        <div class="quick-action-grid">
            @foreach ([['ops.check-in','arrow-in','Check-In','Record a vehicle arrival'],['ops.check-out','arrow-out','Check-Out','Settle and release a vehicle'],['ops.sessions','car','Parked Vehicles','See vehicles currently on site'],['manage.users','users','Manage Users','Accounts, roles and status'],['reports.overview','chart','View Reports','Business and operational reports'],['manage.zones','grid','Manage Zones','Capacity, slots and availability']] as [$route,$icon,$label,$description])
                @if(Route::has($route))<a href="{{ route($route) }}" class="quick-action"><span><x-icon :name="$icon"/></span><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span><x-icon name="chevron-right" class="ml-auto h-4 w-4"/></a>@endif
            @endforeach
        </div>
    </section>

    <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(19rem,.85fr)]">
        <div class="space-y-4">
            <div x-data="{ get fin() { return d.financial_7_days }, get maxDay() { return Math.max(0, ...d.financial_7_days.by_day.map(x => x.amount_minor ?? 0)) } }">@include('pages.dashboards._financial')</div>
            <div x-data="{ get occ() { return d.occupancy }, pct(z) { return z.total ? Math.round((z.occupied + z.reserved) / z.total * 100) : 0 } }">@include('pages.dashboards._occupancy')</div>
        </div>
        <div class="space-y-4">
            <section id="needs-attention" class="card card-pad" aria-labelledby="attention-heading">
                <div class="flex items-center justify-between"><h2 id="attention-heading" class="card-title">Needs Attention</h2><span class="chip-warning" x-show="attentionTotal" x-text="attentionTotal"></span></div>
                <div class="mt-3 space-y-2">
                    <a x-show="d.open_incidents" href="{{ route('manage.incidents') }}" class="attention-item"><span class="status-dot status-dot-danger"></span><span><strong x-text="`${d.open_incidents} open incident${d.open_incidents === 1 ? '' : 's'}`"></strong><small>Review and assign a resolution</small></span><x-icon name="chevron-right"/></a>
                    <a x-show="d.overdue_inspections" href="{{ route('reports.inspections') }}" class="attention-item"><span class="status-dot status-dot-warning"></span><span><strong x-text="`${d.overdue_inspections} overdue safety check${d.overdue_inspections === 1 ? '' : 's'}`"></strong><small>Follow up with the operations team</small></span><x-icon name="chevron-right"/></a>
                    <a x-show="d.pending_refunds" href="{{ route('manage.refunds') }}" class="attention-item"><span class="status-dot status-dot-warning"></span><span><strong x-text="`${d.pending_refunds} pending refund${d.pending_refunds === 1 ? '' : 's'}`"></strong><small>Awaiting an administrative decision</small></span><x-icon name="chevron-right"/></a>
                    <div x-show="!attentionTotal" class="empty-compact"><x-icon name="check"/><span><strong>No current business alerts</strong><small>Incidents, inspections and refunds are clear.</small></span></div>
                </div>
            </section>
            @include('pages.dashboards._anpr-card')
            <section class="card card-pad" aria-labelledby="accounts-heading"><div class="flex items-center justify-between"><h2 id="accounts-heading" class="card-title">Accounts by role</h2><a href="{{ route('manage.users') }}" class="text-xs font-semibold">Manage</a></div><ul class="mt-3 space-y-2 text-sm"><template x-for="r in d.users_by_role" :key="r.role + r.status"><li class="flex justify-between gap-3"><span x-text="$fmt.roleLabel(r.role) + (r.status !== 'active' ? ` (${$fmt.stateLabel('user', r.status).toLowerCase()})` : '')"></span><span class="font-semibold tabular-nums" x-text="r.total"></span></li></template></ul></section>
        </div>
    </div>
</div>
</x-layouts.app>
