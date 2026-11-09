<x-layouts.app title="My vehicles">
<div class="pf-vehicles" x-data="{ ref: null }" x-init="ref = await $store.app.reference()">
<div x-data="crudPage({ url: '/customer/vehicles', syncUrl: false, blank: { plate_number: '', vehicle_category_id: '', fuel_type: '', make: '', model: '', color: '' },
        toForm: (v) => ({ vehicle_category_id: v.vehicle_category_id, fuel_type: v.fuel_type, make: v.make || '', model: v.model || '', color: v.color || '' }),
        create: (d) => ParkFlow.api.post('/customer/vehicles', d), update: (v, d) => ParkFlow.api.patch(`/customer/vehicles/${v.id}`, d),
        successMessage: (mode, r) => mode === 'create' ? `${r.data?.plate_number || r.data?.identifier || 'Vehicle'} added to your account.` : `${r.data?.plate_number || r.data?.identifier || 'Vehicle'} updated.`,
        after: function (r) { if (this.mode === 'create' && r.data?.id) window.location.assign(`/my/vehicles/${encodeURIComponent(r.data.id)}`) } })">
    <x-page-header title="My vehicles" subtitle="Registering your vehicles links their parking sessions and receipts to your account.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>Add vehicle</button>
    </x-page-header>
    <template x-if="loading && !rows.length"><div class="grid gap-3 md:grid-cols-2" role="status"><div class="card card-pad"><div class="skeleton h-6 w-1/2"></div><div class="skeleton mt-3 h-4 w-3/4"></div></div><div class="card card-pad"><div class="skeleton h-6 w-1/2"></div><div class="skeleton mt-3 h-4 w-3/4"></div></div><span class="sr-only">Loading vehicles…</span></div></template>
    <template x-if="error"><div class="alert-danger mb-4 flex flex-wrap items-center justify-between gap-2" role="alert"><span x-text="error"></span><button type="button" class="btn-secondary btn-sm" @click="load()">Try again</button></div></template>
    <div x-data="{ query: '', state: '', get visibleRows() { const q = this.query.trim().toLowerCase(); return rows.filter(v => (!q || [v.plate_number, v.identifier, v.make, v.model, v.color, v.vehicle_category].filter(Boolean).some(x => String(x).toLowerCase().includes(q))) && (!this.state || (this.state === 'parked') === !!v.has_active_session)) } }" x-show="rows.length">
        <div class="card mb-4 flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
            <label class="relative flex-1"><span class="sr-only">Search vehicles</span><x-icon name="search" class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-ink-faint"/><input class="input pl-10" x-model.debounce.200ms="query" placeholder="Search plate, make, model or colour"></label>
            <label><span class="sr-only">Parking state</span><select class="input sm:w-44" x-model="state"><option value="">All vehicles</option><option value="parked">Currently parked</option><option value="not-parked">Not parked</option></select></label>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
        <template x-for="v in visibleRows" :key="v.id"><article class="pf-vehicle-card"><div class="flex items-start justify-between gap-2"><span class="pf-vehicle-plate" x-text="v.plate_number || v.identifier"></span><span :class="v.has_active_session ? 'chip-brand' : 'chip-neutral'" x-text="v.has_active_session ? 'Parked' : 'Not parked'"></span></div>
            <p class="pf-vehicle-details mt-3" x-text="[$fmt.titleCase(v.vehicle_category), v.fuel_type_label, v.make, v.model, v.color].filter(Boolean).join(' | ')"></p>
            <div class="mt-3 flex flex-wrap gap-2"><a class="pf-vehicle-action" :href="`/my/vehicles/${v.id}`">View details</a><a class="pf-vehicle-action" :href="`/my/vehicles/${v.id}#history`">View history</a><button type="button" class="pf-vehicle-action" @click="openEdit(v)">Edit</button>
                <button type="button" class="pf-vehicle-action" :disabled="v.has_active_session" @click="ParkFlow.confirm({ title: `Remove ${v.plate_number || v.identifier}?`, body: 'The vehicle is unlinked from your account. Its parking history and receipts are kept.', confirmLabel: 'Remove', tone: 'danger' }).then(ok => ok && ParkFlow.api.del(`/customer/vehicles/${v.id}`).then(() => { ParkFlow.toast(`${v.plate_number || v.identifier} removed from your account.`); load(); }).catch(e => ParkFlow.toast(e.message, 'danger')))">Remove</button></div></article></template>
        </div><div class="card card-pad text-center text-ink-muted" x-show="!visibleRows.length">No vehicles match your search.</div>
    </div>
    <template x-if="empty"><div class="card card-pad text-ink-muted">No vehicles yet. Add your car, motorcycle or other vehicle.</div></template>
    <x-modal show="modal" title=":mode === 'create' ? 'Add vehicle' : 'Edit vehicle'" panel-class="pf-vehicle-modal">
        <form id="mv-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <template x-if="mode === 'create'"><div class="sm:col-span-2"><label class="label" for="mv-p">Number plate</label><input id="mv-p" class="input-plate" x-model="f.plate_number" maxlength="20" placeholder="WP CAB-1234"><p class="hint">Leave empty only for vehicles without a plate (e.g. bicycle).</p><p class="field-error" x-show="err('plate_number')" x-text="err('plate_number')"></p></div></template>
            <div><label class="label" for="mv-c">Vehicle type</label><select id="mv-c" class="input" x-model="f.vehicle_category_id" required @change="f.fuel_type = ''"><option value="">Choose...</option><template x-for="c in ref?.vehicle_categories || []" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select><p class="field-error" x-show="err('vehicle_category_id')" x-text="err('vehicle_category_id')"></p></div>
            <div><label class="label" for="mv-f2">Fuel type</label><select id="mv-f2" class="input" x-model="f.fuel_type"><option value="">Choose...</option><template x-for="x in (ref?.fuel_types || []).filter(x => (ref?.vehicle_categories.find(c => String(c.id) === String(f.vehicle_category_id))?.allowed_fuel_types || []).includes(x.value))" :key="x.value"><option :value="x.value" x-text="x.label"></option></template></select><p class="field-error" x-show="err('fuel_type')" x-text="err('fuel_type')"></p></div>
            <x-xfield label="Make" model="f.make" name="make" form="$data"/><x-xfield label="Model" model="f.model" name="model" form="$data"/><x-xfield label="Colour" model="f.color" name="color" form="$data"/>
            <p class="text-xs text-ink-muted sm:col-span-2" x-show="mode === 'edit'">The plate cannot be changed. The vehicle type cannot change while it is parked.</p>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="mv-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
    </x-modal>
</div></div>
</x-layouts.app>




