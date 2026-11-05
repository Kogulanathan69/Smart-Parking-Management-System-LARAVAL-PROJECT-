<x-layouts.app title="Staff performance" wide>
<div x-data="dataTable({ url: '/reports/staff-performance', filters: { from: ParkFlow.fmt.todayLocal(-6), to: ParkFlow.fmt.todayLocal() } })">
    <x-page-header title="Staff performance" subtitle="Gate activity per staff member in the period. Counts only — not a quality score.">@include('pages.reports._range')</x-page-header>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Staff</th><th>Status</th><th class="tbl-num">Check-ins</th><th class="tbl-num">Check-outs</th><th class="tbl-num">Collected</th><th class="tbl-num">Inspections</th><th class="tbl-num">Incidents</th><th class="tbl-num">Shifts</th><th class="tbl-num">Late</th></tr></thead><tbody>
        <x-list-states cols="9" empty="No staff activity."/>
        <template x-for="s in rows" :key="s.user_id"><tr><td class="font-semibold" x-text="s.name"></td><td><x-state kind="user" value="s.status"/></td><td class="tbl-num" x-text="s.entries"></td><td class="tbl-num" x-text="s.exits"></td><td class="tbl-num" x-text="$fmt.money(s.collected_minor)"></td><td class="tbl-num" x-text="s.inspections"></td><td class="tbl-num" x-text="s.incidents_reported"></td><td class="tbl-num" x-text="s.shifts_attended"></td><td class="tbl-num" x-text="s.late_check_ins"></td></tr></template>
    </tbody></table></div></div>
</div>
</x-layouts.app>
