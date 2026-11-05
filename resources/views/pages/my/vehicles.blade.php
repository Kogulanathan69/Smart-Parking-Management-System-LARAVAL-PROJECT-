<x-layouts.app title="My vehicles">
<div class="pf-vehicles" x-data="{ ref: null }" x-init="ref = await $store.app.reference()">
<div x-data="crudPage({ url: '/customer/vehicles', syncUrl: false, blank: { plate_number: '', vehicle_category_id: '', fuel_type: '', make: '', model: '', color: '' },
        toForm: (v) => ({ vehicle_category_id: v.vehicle_category_id, fuel_type: v.fuel_type, make: v.make || '', model: v.model || '', color: v.color || '' }),
        create: (d) => ParkFlow.api.post('/customer/vehicles', d), update: (v, d) => ParkFlow.api.patch(`/customer/vehicles/${v.id}`, d) })">
    <x-page-header title="My vehicles" subtitle="Registering your vehicles links their parking sessions and receipts to your account.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>Add vehicle</button>
    </x-page-header>
    <div class="grid gap-3 md:grid-cols-2">
        <template x-for="v in rows" :key="v.id"><article class="pf-vehicle-card"><div class="flex items-start justify-between gap-2"><span class="pf-vehicle-plate" x-text="v.plate_number || v.identifier"></span><span :class="v.has_active_session ? 'chip-brand' : 'chip-neutral'" x-text="v.has_active_session ? 'Parked' : 'Not parked'"></span></div>
            <p class="pf-vehicle-details mt-3" x-text="[$fmt.titleCase(v.vehicle_category), v.fuel_type_label, v.make, v.model, v.color].filter(Boolean).join(' | ')"></p>
            <div class="mt-3 flex gap-2"><button type="button" class="pf-vehicle-action" @click="openEdit(v)">Edit</button>
                <button type="button" class="pf-vehicle-action" :disabled="v.has_active_session" @click="ParkFlow.confirm({ title: `Remove ${v.plate_number || v.identifier}?`, body: 'The vehicle is unlinked from your account. Its parking history and receipts are kept.', confirmLabel: 'Remove', tone: 'danger' }).then(ok => ok && ParkFlow.api.del(`/customer/vehicles/${v.id}`).then(() => { ParkFlow.toast('Vehicle removed.'); load(); }).catch(e => ParkFlow.toast(e.message, 'danger')))">Remove</button></div></article></template>
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




