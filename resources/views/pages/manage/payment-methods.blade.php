<x-layouts.app title="Payment methods">
<div x-data="crudPage({ url: '/admin/payment-methods', blank: { code: '', name: '', confirmation: 'immediate', requires_reference: false, is_active: true },
        toForm: (r) => ({ code: r.code, name: r.name, confirmation: r.confirmation, requires_reference: r.requires_reference, is_active: r.is_active }),
        create: (d) => ParkFlow.api.post('/admin/payment-methods', d), update: (r, d) => ParkFlow.api.post('/admin/payment-methods', { ...d, code: r.code }) })">
    <x-page-header title="Payment methods" subtitle="Methods staff can choose at Check-Out. No card or online payment gateway is connected: 'gateway' methods stay pending until staff confirm them.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>Add method</button>
    </x-page-header>
    <div class="alert-info mb-4"><strong>Settlement configuration.</strong> These options control staff choices only; they do not connect a payment provider.</div>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Code</th><th>Confirmation</th><th>Reference</th><th>Status</th><th></th></tr></thead><tbody>
        <x-list-states cols="6"/>
        <template x-for="m in rows" :key="m.id"><tr><td class="font-semibold" x-text="m.name"></td><td class="font-mono text-xs" x-text="m.code"></td>
            <td x-text="m.confirmation === 'gateway' ? 'Pending until confirmed' : 'Immediate'"></td><td x-text="m.requires_reference ? 'Required' : 'Optional'"></td>
            <td><span :class="m.is_active ? 'chip-success' : 'chip-neutral'" x-text="m.is_active ? 'Active' : 'Inactive'"></span></td><td class="text-right"><button type="button" class="btn-ghost btn-sm" @click="openEdit(m)">Edit</button></td></tr></template>
    </tbody></table></div></div>
    <x-modal show="modal" title=":mode === 'create' ? 'Add payment method' : 'Edit payment method'">
        <form id="pm-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <div><label class="label" for="pm-c">Code</label><input id="pm-c" class="input font-mono" x-model="f.code" :disabled="mode === 'edit'" required maxlength="30"><p class="field-error" x-show="err('code')" x-text="err('code')"></p></div>
            <x-xfield label="Name" model="f.name" name="name" form="$data" required/>
            <div><label class="label" for="pm-k">Confirmation</label><select id="pm-k" class="input" x-model="f.confirmation"><option value="immediate">Immediate (cash, manual card terminal)</option><option value="gateway">Pending until confirmed</option></select></div>
            <div class="space-y-2 pt-6"><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.requires_reference"> Reference required</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_active"> Active</label></div>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="pm-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
    </x-modal>
</div>
</x-layouts.app>
