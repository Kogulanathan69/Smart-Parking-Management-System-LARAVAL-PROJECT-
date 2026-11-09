<x-layouts.app title="My shifts">
<div x-data="myShifts()">
    <x-page-header title="My shifts" subtitle="Start your shift when you arrive and end it when you leave. Times are recorded by the server."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <div class="summary-strip" x-show="!loading"><div class="kpi"><span class="kpi-value" x-text="rows.length"></span><span class="kpi-label">Published assignments</span></div><div class="kpi"><span class="kpi-value text-success-ink" x-text="rows.filter(a => a.attendance?.check_in_at && !a.attendance?.check_out_at).length"></span><span class="kpi-label">Shift in progress</span></div></div>
    <div class="grid gap-3 md:grid-cols-2">
        <template x-for="a in rows" :key="a.id">
            <section class="card card-pad"><div class="flex items-start justify-between gap-2"><div><h2 class="card-title" x-text="a.shift?.name"></h2><p class="text-sm text-ink-muted" x-text="`${$fmt.date(a.shift_date)} · ${a.shift?.start_time}–${a.shift?.end_time}${a.is_gate_duty ? ' · gate duty' : ''}`"></p></div>
                <x-state kind="shift" value="a.attendance?.check_out_at ? 'checked_out' : (a.attendance?.check_in_at ? 'checked_in' : a.status)"/></div>
                <dl class="dl mt-3"><dt>Started</dt><dd x-text="$fmt.dateTime(a.attendance?.check_in_at)"></dd><dt>Ended</dt><dd x-text="$fmt.dateTime(a.attendance?.check_out_at)"></dd><dt x-show="a.attendance?.status">Attendance</dt><dd x-show="a.attendance?.status" x-text="$fmt.titleCase(a.attendance?.status)"></dd></dl>
                <div class="mt-3 flex gap-2" x-show="a.status === 'scheduled'">
                    <button type="button" class="btn-primary" x-show="!a.attendance?.check_in_at" @click="act(a, 'in')" :disabled="busy">Start shift</button>
                    <button type="button" class="btn-secondary" x-show="a.attendance?.check_in_at && !a.attendance?.check_out_at" @click="act(a, 'out')" :disabled="busy">End shift</button>
                </div></section>
        </template>
    </div>
    <p x-show="!loading && !rows.length" class="card card-pad text-ink-muted">You have no upcoming shifts. Your admin publishes the roster.</p>
</div>
</x-layouts.app>
