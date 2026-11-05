<x-layouts.app title="Vehicle" :crumbs="['Vehicle register' => route('ops.vehicles')]">
<div x-data="{ v: null, owner: null, sessions: [], loading: true, error: null, notFound: false, ref: null, edit: false, busy: false, f: {},
    async reload() { this.loading = true; try { const r = await ParkFlow.api.get('/staff/vehicles/{{ $id }}'); this.v = r.data; this.owner = r.owner; this.sessions = r.recent_sessions || []; this.error = null; } catch (e) { this.error = e.message; this.notFound = e.status === 404; } finally { this.loading = false; } },
    async init() { this.ref = await $store.app.reference(); await this.reload(); },
    startEdit() { this.f = { vehicle_category_id: this.v.vehicle_category_id, fuel_type: this.v.fuel_type, make: this.v.make || '', model: this.v.model || '', color: this.v.color || '', owner_id: this.v.owner_id ?? '' }; this.edit = true; },
    async save() { this.busy = true; try { const b = { ...this.f, owner_id: this.f.owner_id === '' ? null : Number(this.f.owner_id) }; await ParkFlow.api.patch('/admin/vehicles/{{ $id }}', b); ParkFlow.toast('Vehicle updated.'); this.edit = false; await this.reload(); } catch (e) { ParkFlow.toast(e.message, 'danger'); } finally { this.busy = false; } } }">
    @include('pages.ops._record-states', ['var' => 'v'])
    <template x-if="v"><div>
        <x-page-header title="Vehicle"><button type="button" class="btn-secondary" x-show="$store.app.can('admin','super_admin')" @click="startEdit()">Edit (admin)</button></x-page-header>
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card card-pad"><span class="plate text-xl" x-text="v.plate_number || v.identifier"></span>
                <dl class="dl mt-4"><dt>Identifier</dt><dd class="font-mono" x-text="v.identifier"></dd><dt>Category</dt><dd x-text="$fmt.titleCase(v.vehicle_category)"></dd><dt>Fuel</dt><dd x-text="v.fuel_type_label"></dd>
                    <dt>Make / model</dt><dd x-text="[v.make, v.model].filter(Boolean).join(' ') || '—'"></dd><dt>Colour</dt><dd x-text="v.color || '—'"></dd>
                    <dt>Owner</dt><dd x-text="owner ? `${owner.name}${owner.phone ? ' · ' + owner.phone : ''}` : 'Not linked to an account'"></dd></dl></section>
            <section class="card lg:col-span-2"><h2 class="card-title px-4 pt-4">Recent sessions</h2>
                <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>Reference</th><th>Status</th><th>Checked in</th><th>Checked out</th><th class="tbl-num">Charge</th></tr></thead><tbody>
                    <template x-for="s in sessions" :key="s.uuid"><tr><td><a :href="`/ops/sessions/${s.uuid}`" x-text="s.reference"></a></td><td><x-state kind="session" value="s.status"/></td><td x-text="$fmt.dateTime(s.entry_at)"></td><td x-text="$fmt.dateTime(s.exit_at)"></td><td class="tbl-num" x-text="$fmt.money(s.total_charge_minor)"></td></tr></template>
                    <tr x-show="!sessions.length"><td colspan="5" class="py-4 text-center text-ink-muted">No sessions.</td></tr></tbody></table></div></section>
        </div>
        <x-modal show="edit" title="Edit vehicle">
            <form id="ve-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
                <div><label class="label" for="ve-c">Category</label><select id="ve-c" class="input" x-model="f.vehicle_category_id"><template x-for="c in ref?.vehicle_categories || []" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select></div>
                <div><label class="label" for="ve-f2">Fuel</label><select id="ve-f2" class="input" x-model="f.fuel_type"><template x-for="x in ref?.fuel_types || []" :key="x.value"><option :value="x.value" x-text="x.label"></option></template></select></div>
                <div><label class="label" for="ve-m">Make</label><input id="ve-m" class="input" x-model="f.make" maxlength="50"></div>
                <div><label class="label" for="ve-mo">Model</label><input id="ve-mo" class="input" x-model="f.model" maxlength="50"></div>
                <div><label class="label" for="ve-co">Colour</label><input id="ve-co" class="input" x-model="f.color" maxlength="30"></div>
                <div><label class="label" for="ve-o">Owner account ID</label><input id="ve-o" class="input" inputmode="numeric" x-model="f.owner_id"><p class="hint">A customer account number, or empty to unlink.</p></div>
                <p class="text-xs text-ink-muted sm:col-span-2">Past sessions keep the category and fuel type recorded at their Check-In.</p>
            </form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="edit = false">Cancel</button><button form="ve-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
        </x-modal>
    </div></template>
</div>
</x-layouts.app>
