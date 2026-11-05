<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    <x-kpi label="Parked now" value="d.active_sessions" href="{{ route('ops.sessions') }}"/>
    <x-kpi label="Check-ins today" value="d.entries_today"/>
    <x-kpi label="Check-outs today" value="d.exits_today"/>
    <x-kpi label="Revenue today" value="$fmt.money(d.revenue_today_minor)" hint="Settled payments, Sri Lanka day"/>
    <x-kpi label="Open incidents" value="d.open_incidents" href="{{ route('manage.incidents') }}"/>
    <x-kpi label="Overdue safety checks" value="d.overdue_inspections" href="{{ route('reports.inspections') }}"/>
</div>
