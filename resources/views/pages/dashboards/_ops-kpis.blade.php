<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    <x-kpi label="Parked now" value="d.active_sessions" icon="car" href="{{ route('ops.sessions') }}"/>
    <x-kpi label="Check-ins today" value="d.entries_today" icon="arrow-in"/>
    <x-kpi label="Check-outs today" value="d.exits_today" icon="arrow-out"/>
    <x-kpi label="Revenue today" value="$fmt.money(d.revenue_today_minor)" icon="cash" hint="Settled payments, Sri Lanka day"/>
    <x-kpi label="Open incidents" value="d.open_incidents" icon="alert" href="{{ route('manage.incidents') }}"/>
    <x-kpi label="Overdue safety checks" value="d.overdue_inspections" icon="shield" href="{{ route('reports.inspections') }}"/>
</div>
