<x-layouts.app title="Reports" wide>
<div x-data="{ d: null, error: null, async init() { try { this.d = (await ParkFlow.api.get('/reports/overview')).data } catch (e) { this.error = e.message } }, pct(z) { return z.total ? Math.round((z.occupied + z.reserved) / z.total * 100) : 0 }, get occ() { return this.d?.occupancy || [] } }">
    <x-page-header title="Reports overview" subtitle="Today's operation in Sri Lanka time. Every report is read-only."><button type="button" class="btn-secondary no-print" @click="window.print()"><x-icon name="printer" class="h-4 w-4"/>Print</button></x-page-header>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="d"><div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <x-kpi label="Parked now" value="d.active_sessions"/><x-kpi label="Check-ins today" value="d.entries_today"/><x-kpi label="Check-outs today" value="d.exits_today"/>
            <x-kpi label="Revenue today" value="$fmt.money(d.revenue_today_minor)"/><x-kpi label="Open incidents" value="d.open_incidents"/><x-kpi label="Overdue safety checks" value="d.overdue_inspections"/>
        </div>
        <div class="mt-4">@include('pages.dashboards._occupancy')</div>
    </div></template>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 no-print">
        @foreach ([['reports.financial','Financial','Revenue, methods, refunds, penalties'],['reports.payments','Payments ledger','Every payment with method and reference'],['reports.sessions','Session ledger','Check-In / Check-Out records'],['reports.calendar','Calendar','Daily entries, exits and revenue'],['reports.occupancy','Occupancy','Zones and vehicle types'],['reports.inspections','Safety inspections','Completed, due and missed'],['reports.staff','Staff performance','Gate activity per staff member'],['reports.anpr','ANPR & cameras','Recognition counts and camera health']] as [$r, $t, $d])
            <a href="{{ route($r) }}" class="card card-pad hover:border-brand-strong"><span class="block font-semibold text-ink">{{ $t }}</span><span class="text-sm text-ink-muted">{{ $d }}</span></a>
        @endforeach
    </div>
</div>
</x-layouts.app>
