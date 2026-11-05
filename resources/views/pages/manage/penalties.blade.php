<x-layouts.app title="Penalty types">
<div x-data="crudPage({ url: '/admin/penalty-types', blank: { code: '', name: '', description: '', amount: '', is_active: true },
        toForm: (r) => ({ name: r.name, description: r.description || '', amount: ParkFlow.fmt.minorToRupeesInput(r.amount_minor), is_active: r.is_active }),
        create: (d) => ParkFlow.api.post('/admin/penalty-types', { code: d.code, name: d.name, description: d.description || null, amount_minor: ParkFlow.fmt.rupeesToMinor(d.amount), is_active: d.is_active }),
        update: (r, d) => ParkFlow.api.patch(`/admin/penalty-types/${r.id}`, { name: d.name, description: d.description || null, amount_minor: ParkFlow.fmt.rupeesToMinor(d.amount), is_active: d.is_active }) })">
    <x-page-header title="Penalty types" subtitle="Penalties are applied by staff with a reason, never automatically. Changing an amount does not affect penalties already applied.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>New penalty type</button>
    </x-page-header>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Code</th><th class="tbl-num">Amount</th><th>Description</th><th>Status</th><th></th></tr></thead><tbody>
        <x-list-states cols="6"/>
        <template x-for="t in rows" :key="t.id"><tr><td class="font-semibold" x-text="t.name"></td><td class="font-mono text-xs" x-text="t.code"></td><td class="tbl-num" x-text="$fmt.money(t.amount_minor)"></td><td class="text-sm text-ink-muted" x-text="t.description || '—'"></td>
            <td><span :class="t.is_active ? 'chip-success' : 'chip-neutral'" x-text="t.is_active ? 'Active' : 'Inactive'"></span></td><td class="text-right"><button type="button" class="btn-ghost btn-sm" @click="openEdit(t)">Edit</button></td></tr></template>
    </tbody></table></div></div>
    <x-modal show="modal" title=":mode === 'create' ? 'New penalty type' : 'Edit penalty type'">
        <form id="pt-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <template x-if="mode === 'create'"><x-xfield label="Code" model="f.code" name="code" form="$data" required hint="lowercase, e.g. overnight_unauthorised"/></template>
            <x-xfield label="Name" model="f.name" name="name" form="$data" required/>
            <div><label class="label" for="pt-a">Amount (LKR)</label><input id="pt-a" class="input" inputmode="decimal" x-model="f.amount" required><p class="field-error" x-show="err('amount_minor')" x-text="err('amount_minor')"></p></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_active"> Active</label>
            <x-xfield class="sm:col-span-2" label="Description" model="f.description" name="description" type="textarea" form="$data"/>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="pt-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
