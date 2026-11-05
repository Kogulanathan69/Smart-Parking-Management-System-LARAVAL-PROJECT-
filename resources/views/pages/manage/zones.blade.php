<x-layouts.app title="Zones & slots" wide>
<div x-data="{ ref: null }" x-init="ref = await $store.app.reference()">
<div x-data="crudPage({ url: '/admin/zones', blank: { code: '', name: '', zone_type_id: '', description: '', is_active: true, vehicle_category_ids: [] }, create: (d) => ParkFlow.api.post('/admin/zones', d) })">
    <x-page-header title="Zones & slots" subtitle="Zones group slots by parking type. Slot counts are set per vehicle category on each zone.">
        <button type="button" class="btn-primary" @click="openCreate()"><x-icon name="plus" class="h-4 w-4"/>New zone</button>
    </x-page-header>
    <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Zone</th><th>Type</th><th>Vehicles</th><th class="tbl-num">Slots</th><th class="tbl-num">Free</th><th>Status</th></tr></thead><tbody>
        <x-list-states cols="6" empty="No zones yet."/>
        <template x-for="z in rows" :key="z.id"><tr>
            <td><a :href="`/manage/zones/${z.id}`" class="font-semibold" x-text="`Zone ${z.code}`"></a><span class="block text-xs text-ink-muted" x-text="z.name"></span></td>
            <td x-text="z.zone_type?.name"></td><td class="text-xs" x-text="(z.vehicle_categories || []).map($fmt.titleCase).join(', ')"></td>
            <td class="tbl-num" x-text="(z.occupancy || []).reduce((a, o) => a + o.total, 0)"></td><td class="tbl-num" x-text="(z.occupancy || []).reduce((a, o) => a + o.available, 0)"></td>
            <td><span :class="z.is_active ? 'chip-success' : 'chip-neutral'" x-text="z.is_active ? 'Active' : 'Inactive'"></span></td></tr></template>
    </tbody></table></div></div>
    <x-modal show="modal" title="New zone">
        <form id="zc-f" class="grid gap-3 sm:grid-cols-2" @submit.prevent="save()">
            <x-xfield label="Code" model="f.code" name="code" form="$data" required hint="Short, e.g. F. Cannot be changed later."/>
            <x-xfield label="Name" model="f.name" name="name" form="$data" required/>
            <div class="sm:col-span-2"><label class="label" for="zc-t">Zone type</label><select id="zc-t" class="input" x-model="f.zone_type_id" required><option value="">Choose…</option><template x-for="t in ref?.zone_types || []" :key="t.id"><option :value="t.id" x-text="t.name"></option></template></select>
                <p class="field-error" x-show="err('zone_type_id')" x-text="err('zone_type_id')"></p></div>
            <fieldset class="sm:col-span-2"><legend class="label">Vehicle categories allowed</legend><div class="flex flex-wrap gap-3">
                <template x-for="c in ref?.vehicle_categories || []" :key="c.id"><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" :value="c.id" x-model.number="f.vehicle_category_ids"><span x-text="c.name"></span></label></template></div>
                <p class="field-error" x-show="err('vehicle_category_ids')" x-text="err('vehicle_category_ids')"></p></fieldset>
            <x-xfield class="sm:col-span-2" label="Description" model="f.description" name="description" type="textarea" form="$data"/>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_active"> Active</label>
            <p class="text-xs text-ink-muted sm:col-span-2">After creating, open the zone to set the number of slots for each vehicle category.</p>
        </form>
        <x-slot:footer><button type="button" class="btn-secondary" @click="modal = false">Cancel</button><button form="zc-f" class="btn-primary" :disabled="busy">Create zone</button></x-slot:footer>
    </x-modal>
</div></div>
</x-layouts.app>
