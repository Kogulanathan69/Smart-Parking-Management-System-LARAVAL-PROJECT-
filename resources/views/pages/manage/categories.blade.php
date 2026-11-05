<x-layouts.app title="Vehicle categories">
<div x-data="crudPage({ url: '/admin/vehicle-categories', blank: { code: '', name: '', requires_plate: true, is_motorized: true },
        toForm: (r) => ({ name: r.name, requires_plate: r.requires_plate, is_motorized: r.is_motorized, is_active: r.is_active, sort_order: r.sort_order }),
        create: (d) => ParkFlow.api.post('/admin/vehicle-categories', d), update: (r, d) => ParkFlow.api.patch(`/admin/vehicle-categories/${r.id}`, d) })">
    <x-page-header title="Vehicle categories" subtitle="Categories are never deleted, only deactivated. Vehicles already parked in a deactivated category can still check out.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>New category</button>
    </x-page-header>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Order</th><th>Name</th><th>Code</th><th>Plate</th><th>Motorised</th><th>Fuel types</th><th>Status</th><th></th></tr></thead><tbody>
        <x-list-states cols="8"/>
        <template x-for="c in rows" :key="c.id"><tr><td class="tbl-num" x-text="c.sort_order"></td><td class="font-semibold" x-text="c.name"></td><td class="font-mono text-xs" x-text="c.code"></td>
            <td x-text="c.requires_plate ? 'Required' : 'Not required'"></td><td x-text="c.is_motorized ? 'Yes' : 'No'"></td><td class="text-xs" x-text="(c.allowed_fuel_types || []).map($fmt.titleCase).join(', ')"></td>
            <td><span :class="c.is_active ? 'chip-success' : 'chip-neutral'" x-text="c.is_active ? 'Active' : 'Inactive'"></span></td>
            <td class="text-right"><button type="button" class="btn-ghost btn-sm" @click="openEdit(c)">Edit</button></td></tr></template>
    </tbody></table></div></div>
    <x-modal show="modal" title=":mode === 'create' ? 'New category' : 'Edit category'">
        <form id="cat-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <template x-if="mode === 'create'"><div><label class="label" for="cat-c">Code</label><input id="cat-c" class="input font-mono" x-model="f.code" required maxlength="30" pattern="[a-z0-9_]+"><p class="hint">Lowercase, e.g. three_wheeler. Cannot change later.</p><p class="field-error" x-show="err('code')" x-text="err('code')"></p></div></template>
            <x-xfield label="Name" model="f.name" name="name" form="$data" required/>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.requires_plate"> Number plate required</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_motorized"> Motorised (has a fuel type)</label>
            <template x-if="mode === 'edit'"><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_active"> Active</label></template>
            <template x-if="mode === 'edit'"><div><label class="label" for="cat-o">Sort order</label><input id="cat-o" type="number" class="input" x-model.number="f.sort_order" min="0"></div></template>
            <p class="text-xs text-ink-muted sm:col-span-2">Changes that would make existing vehicles invalid are refused by the server.</p>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="cat-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
