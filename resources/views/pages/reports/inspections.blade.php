<x-layouts.app title="Safety inspections" wide>
<div x-data="dataTable({ url: '/reports/inspections', filters: { from: ParkFlow.fmt.todayLocal(-6), to: ParkFlow.fmt.todayLocal(), status: '' }, perPage: 50 })">
    <x-page-header title="Safety inspections" subtitle="Scheduled, completed and missed inspections in the period.">
        @component('pages.reports._range')<div><label class="label" for="ir-s">Status</label><select id="ir-s" class="input" x-model="filters.status"><option value="">All</option><option value="scheduled">Due</option><option value="completed">Completed</option><option value="missed">Missed</option></select></div>@endcomponent
    </x-page-header>
    <div class="summary-strip" x-show="loaded"><div class="kpi"><span class="kpi-value" x-text="total || rows.length"></span><span class="kpi-label">Matching inspections</span></div><div class="kpi"><span class="kpi-value text-success-ink" x-text="rows.filter(i => i.status === 'completed').length"></span><span class="kpi-label">Completed on this page</span></div><div class="kpi"><span class="kpi-value text-danger-ink" x-text="rows.filter(i => i.status === 'missed').length"></span><span class="kpi-label">Missed on this page</span></div></div>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Scheduled</th><th>Session</th><th>Type</th><th>Status</th><th>Performed</th><th>Condition</th><th>New damage</th><th>Notes</th></tr></thead><tbody>
        <x-list-states cols="8" empty="No inspections in this period."/>
        <template x-for="i in rows" :key="i.id"><tr><td x-text="$fmt.dateTime(i.scheduled_for)"></td><td x-text="i.session_reference || '—'"></td><td x-text="$fmt.titleCase(i.type)"></td><td><x-state kind="inspection" value="i.status"/></td><td x-text="$fmt.dateTime(i.performed_at)"></td>
            <td x-text="i.condition ? $fmt.stateLabel('condition', i.condition) : '—'"></td><td x-text="i.new_damage_found ? 'Yes' : 'No'"></td><td class="text-sm" x-text="i.notes || '—'"></td></tr></template>
    </tbody></table></div><x-pager/></div>
</div>
</x-layouts.app>
