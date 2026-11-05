<x-layouts.app title="Shifts & roster" wide>
<div x-data="{ tab: 'roster', staff: [], async init() { try { this.staff = (await ParkFlow.api.get('/admin/users', { role: 'staff', status: 'active', per_page: 100 })).data; } catch { this.staff = []; } } }">
    <x-page-header title="Shifts & roster" subtitle="Shift templates, staff assignments, coverage gaps and attendance. Times are Sri Lanka time."/>
    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-line" role="tablist">
        @foreach (['roster' => 'Roster', 'coverage' => 'Coverage gaps', 'attendance' => 'Attendance', 'templates' => 'Shift templates'] as $k => $l)
            <button type="button" role="tab" class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold" :aria-selected="(tab === '{{ $k }}').toString()" :class="tab === '{{ $k }}' ? 'border-brand-strong text-brand-stronger' : 'border-transparent text-ink-muted'" @click="tab = '{{ $k }}'">{{ $l }}</button>
        @endforeach
    </div>

    <div x-show="tab === 'roster'" x-data="{ shifts: [] }" x-init="shifts = (await ParkFlow.api.get('/admin/shifts')).data">
        <div x-data="crudPage({ url: '/admin/shift-assignments', filters: { from: ParkFlow.fmt.todayLocal(), to: ParkFlow.fmt.todayLocal(6) }, perPage: 100, syncUrl: false,
                blank: { shift_id: '', user_id: '', shift_date: ParkFlow.fmt.todayLocal(), is_gate_duty: true, notes: '' }, create: (d) => ParkFlow.api.post('/admin/shift-assignments', d) })">
            <form class="card mb-4 flex flex-wrap items-end gap-3 p-4" @submit.prevent="search()">
                <div><label class="label" for="ro-f">From</label><input id="ro-f" type="date" class="input" x-model="filters.from"></div>
                <div><label class="label" for="ro-t">To</label><input id="ro-t" type="date" class="input" x-model="filters.to"></div>
                <button class="btn-secondary">Show</button><button type="button" class="btn-primary ml-auto" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>Assign staff</button>
            </form>
            <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Shift</th><th>Staff</th><th>Gate duty</th><th>Status</th><th>Attendance</th><th></th></tr></thead><tbody>
                <x-list-states cols="7" empty="Nobody is rostered in this period."/>
                <template x-for="a in rows" :key="a.id"><tr><td x-text="$fmt.date(a.shift_date)"></td><td x-text="`${a.shift?.name} (${a.shift?.start_time}–${a.shift?.end_time})`"></td><td x-text="a.user?.name"></td><td x-text="a.is_gate_duty ? 'Yes' : 'No'"></td>
                    <td><x-state kind="shift" value="a.status"/></td><td class="text-xs" x-text="a.attendance ? `${$fmt.time(a.attendance.check_in_at)}–${a.attendance.check_out_at ? $fmt.time(a.attendance.check_out_at) : '…'} · ${$fmt.titleCase(a.attendance.status)}` : '—'"></td>
                    <td class="text-right"><button type="button" class="btn-ghost btn-sm" x-show="a.status === 'scheduled'" @click="ParkFlow.confirm({ title: `Approve leave for ${a.user?.name}?`, body: 'Optionally enter the user ID of a replacement staff member.', input: 'Replacement user ID (optional)', confirmLabel: 'Approve leave' }).then(v => v !== false && ParkFlow.api.post(`/admin/shift-assignments/${a.id}/leave`, v ? { replacement_user_id: Number(v) } : {}).then(() => { ParkFlow.toast('Leave approved.'); load(); }).catch(e => ParkFlow.toast(e.message, 'danger')))">Approve leave</button></td></tr></template>
            </tbody></table></div></div>
            <x-modal show="modal" title="Assign staff to a shift">
                <form id="as-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
                    <div><label class="label" for="as-d">Date</label><input id="as-d" type="date" class="input" x-model="f.shift_date" required></div>
                    <div><label class="label" for="as-s">Shift</label><select id="as-s" class="input" x-model="f.shift_id" required><option value="">Choose…</option><template x-for="s in shifts" :key="s.id"><option :value="s.id" x-text="`${s.name} ${s.start_time}–${s.end_time}`"></option></template></select></div>
                    <div class="sm:col-span-2"><label class="label" for="as-u">Staff member</label><select id="as-u" class="input" x-model="f.user_id" required><option value="">Choose…</option><template x-for="u in staff" :key="u.id"><option :value="u.id" x-text="u.name"></option></template></select><p class="field-error" x-show="err('user_id')" x-text="err('user_id')"></p></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_gate_duty"> Gate duty</label>
                    <x-xfield class="sm:col-span-2" label="Notes" model="f.notes" name="notes" form="$data"/>
                    <p class="field-error sm:col-span-2" x-show="err('shift_date')" x-text="err('shift_date')"></p>
                </form>
                <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="as-f" class="btn-primary" :disabled="busy">Assign</button></x-slot:footer>
            </x-modal>
        </div>
    </div>

    <div x-show="tab === 'coverage'" x-cloak x-data="{ date: ParkFlow.fmt.todayLocal(), gaps: null, async load() { try { this.gaps = (await ParkFlow.api.get('/admin/shift-coverage', { date: this.date })).gaps; } catch (e) { ParkFlow.toast(e.message, 'danger'); } } }" x-init="load()">
        <div class="card card-pad"><form class="flex items-end gap-2" @submit.prevent="load()"><div><label class="label" for="cv-d">Date</label><input id="cv-d" type="date" class="input" x-model="date"></div><button class="btn-secondary">Check</button></form>
            <div class="table-wrap mt-4"><table class="tbl"><thead><tr><th>Shift</th><th class="tbl-num">Gate staff required</th><th class="tbl-num">Scheduled</th><th>Status</th></tr></thead><tbody>
                <template x-for="g in gaps || []" :key="g.shift"><tr><td class="capitalize" x-text="g.shift"></td><td class="tbl-num" x-text="g.required"></td><td class="tbl-num" x-text="g.scheduled"></td><td><span :class="g.scheduled >= g.required ? 'chip-success' : 'chip-danger'" x-text="g.scheduled >= g.required ? 'Covered' : `Short by ${g.required - g.scheduled}`"></span></td></tr></template>
                <tr x-show="gaps && !gaps.length"><td colspan="4" class="text-ink-muted">All shifts are covered.</td></tr></tbody></table></div></div>
    </div>

    <div x-show="tab === 'attendance'" x-cloak>
        <div x-data="dataTable({ url: '/admin/attendance', perPage: 50, syncUrl: false })"><div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Staff (user ID)</th><th>Checked in</th><th>Checked out</th><th>Status</th></tr></thead><tbody>
            <x-list-states cols="4" empty="No attendance recorded yet."/>
            <template x-for="a in rows" :key="a.id"><tr><td x-text="a.user_id"></td><td x-text="$fmt.dateTime(a.check_in_at)"></td><td x-text="$fmt.dateTime(a.check_out_at)"></td><td x-text="$fmt.titleCase(a.status)"></td></tr></template>
        </tbody></table></div><x-pager/></div></div>
    </div>

    <div x-show="tab === 'templates'" x-cloak>
        <div x-data="crudPage({ url: '/admin/shifts', syncUrl: false, blank: { code: '', name: '', start_time: '06:00', end_time: '14:00', min_gate_staff: 1, is_active: true },
                toForm: (r) => ({ code: r.code, name: r.name, start_time: r.start_time, end_time: r.end_time, min_gate_staff: r.min_gate_staff, is_active: r.is_active }),
                create: (d) => ParkFlow.api.post('/admin/shifts', d), update: (r, d) => ParkFlow.api.put(`/admin/shifts/${r.id}`, d) })">
            <div class="mb-3 flex justify-end"><button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>New shift template</button></div>
            <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Code</th><th>Hours</th><th class="tbl-num">Min. gate staff</th><th>Status</th><th></th></tr></thead><tbody>
                <x-list-states cols="6"/>
                <template x-for="s in rows" :key="s.id"><tr><td class="font-semibold" x-text="s.name"></td><td class="font-mono text-xs" x-text="s.code"></td><td x-text="`${s.start_time}–${s.end_time}`"></td><td class="tbl-num" x-text="s.min_gate_staff"></td>
                    <td><span :class="s.is_active ? 'chip-success' : 'chip-neutral'" x-text="s.is_active ? 'Active' : 'Inactive'"></span></td><td class="text-right"><button type="button" class="btn-ghost btn-sm" @click="openEdit(s)">Edit</button></td></tr></template>
            </tbody></table></div></div>
            <x-modal show="modal" title=":mode === 'create' ? 'New shift template' : 'Edit shift template'">
                <form id="sh-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
                    <x-xfield label="Code" model="f.code" name="code" form="$data" required/>
                    <x-xfield label="Name" model="f.name" name="name" form="$data" required/>
                    <x-xfield label="Starts" model="f.start_time" name="start_time" type="time" form="$data" required/>
                    <x-xfield label="Ends" model="f.end_time" name="end_time" type="time" form="$data" required/>
                    <div><label class="label" for="sh-m">Minimum gate staff</label><input id="sh-m" type="number" min="0" max="20" class="input" x-model.number="f.min_gate_staff"></div>
                    <label class="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" class="check" x-model="f.is_active"> Active</label>
                </form>
                <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="sh-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
            </x-modal>
        </div>
    </div>
</div>
</x-layouts.app>
