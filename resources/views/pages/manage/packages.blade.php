<x-layouts.app title="Packages & rates" wide>
<div x-data="{ ref: null }" x-init="ref = await $store.app.reference()">
<div x-data="crudPage({ url: '/admin/packages', blank: { code: '', name: '', billing_mode: 'fixed', duration_minutes: 1440, zone_type_ids: [], allows_early_exit_credit: false, guarantees_slot: false, is_active: true, description: '' },
        create: (d) => ParkFlow.api.post('/admin/packages', { ...d, duration_minutes: d.billing_mode === 'fixed' ? d.duration_minutes : null }) })">
    <x-page-header title="Packages & rates" subtitle="Rates are versioned: publishing a new rate never changes vehicles already parked. All prices in LKR.">
        <a href="{{ route('manage.pricing') }}" class="btn-secondary"><x-icon name="calculator" class="h-4 w-4"/>Price calculator</a>
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>New package</button>
    </x-page-header>
    <div class="grid gap-4 lg:grid-cols-2">
        <template x-for="p in rows" :key="p.id">
            <section class="card card-pad"><div class="flex items-start justify-between gap-2"><div><a :href="`/manage/packages/${p.id}`" class="text-lg font-semibold" x-text="p.name"></a>
                <p class="text-xs text-ink-muted" x-text="`${p.code} · ${$fmt.titleCase(p.billing_mode)}${p.duration_minutes ? ' · ' + $fmt.minutes(p.duration_minutes) : ''}${p.guarantees_slot ? ' · guaranteed slot' : ''}`"></p></div>
                <span :class="p.is_active ? 'chip-success' : 'chip-neutral'" x-text="p.is_active ? 'Active' : 'Inactive'"></span></div>
                <div class="table-wrap mt-3"><table class="tbl"><thead><tr><th>Vehicle</th><th class="tbl-num">Price</th><th class="tbl-num">Overtime / h</th><th class="tbl-num">Grace</th></tr></thead><tbody>
                    <template x-for="r in p.current_rates" :key="r.id"><tr><td x-text="ref?.vehicle_categories.find(c => c.id === r.vehicle_category_id)?.name || r.vehicle_category_id"></td><td class="tbl-num" x-text="$fmt.money(r.price_minor)"></td><td class="tbl-num" x-text="$fmt.money(r.overtime_hourly_minor)"></td><td class="tbl-num" x-text="`${r.grace_minutes ?? 0} min`"></td></tr></template>
                    <tr x-show="!p.current_rates.length"><td colspan="4" class="text-ink-muted">No rates published.</td></tr></tbody></table></div></section>
        </template>
    </div>
    <x-modal show="modal" title="New package" size="max-w-2xl">
        <form id="pk-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <x-xfield label="Code" model="f.code" name="code" form="$data" required hint="e.g. fortnightly"/>
            <x-xfield label="Name" model="f.name" name="name" form="$data" required/>
            <div><label class="label" for="pk-m">Billing mode</label><select id="pk-m" class="input" x-model="f.billing_mode"><option value="hourly">Hourly</option><option value="fixed">Fixed duration</option><option value="night">Night</option><option value="addon">Add-on</option></select></div>
            <div x-show="f.billing_mode === 'fixed'"><label class="label" for="pk-d">Duration (minutes)</label><input id="pk-d" type="number" class="input" x-model.number="f.duration_minutes" min="1"><p class="hint" x-text="$fmt.minutes(f.duration_minutes)"></p><p class="field-error" x-show="err('duration_minutes')" x-text="err('duration_minutes')"></p></div>
            <fieldset class="sm:col-span-2"><legend class="label">Allowed in zone types</legend><div class="flex flex-wrap gap-3"><template x-for="t in ref?.zone_types || []" :key="t.id"><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" :value="t.id" x-model.number="f.zone_type_ids"><span x-text="t.name"></span></label></template></div></fieldset>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.guarantees_slot" :disabled="['hourly','addon'].includes(f.billing_mode)"> Guarantees a dedicated slot</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.allows_early_exit_credit"> Early exit earns credit</label>
            <x-xfield class="sm:col-span-2" label="Description" model="f.description" name="description" type="textarea" form="$data"/>
            <p class="text-xs text-ink-muted sm:col-span-2">Publish prices for each vehicle category on the package page after creating it.</p>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="pk-f" class="btn-primary" :disabled="busy">Create package</button></x-slot:footer>
    </x-modal>
</div></div>
</x-layouts.app>
