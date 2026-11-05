<x-layouts.app title="Zone" :crumbs="['Zones & slots' => route('manage.zones')]" wide>
<div x-data="zoneDetail({{ (int) $id }})">
    @include('pages.ops._record-states', ['var' => 'z'])
    <template x-if="z"><div>
        <x-page-header title="Zone"><button type="button" class="btn-secondary" @click="startEdit()">Edit zone</button></x-page-header>
        <div class="grid gap-4 xl:grid-cols-3">
            <section class="card card-pad"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold" x-text="`Zone ${z.code}`"></h2><span :class="z.is_active ? 'chip-success' : 'chip-neutral'" x-text="z.is_active ? 'Active' : 'Inactive'"></span></div>
                <dl class="dl mt-3"><dt>Name</dt><dd x-text="z.name"></dd><dt>Type</dt><dd x-text="z.zone_type?.name"></dd><dt>Description</dt><dd x-text="z.description || '—'"></dd></dl></section>
            <section class="card xl:col-span-2"><h2 class="card-title px-4 pt-4">Capacity by vehicle category</h2>
                <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>Category</th><th class="tbl-num">Available</th><th class="tbl-num">Occupied</th><th class="tbl-num">Reserved</th><th class="tbl-num">Out of service</th><th>Slots (capacity)</th></tr></thead><tbody>
                    <template x-for="o in z.occupancy || []" :key="o.vehicle_category"><tr>
                        <td x-text="$fmt.titleCase(o.vehicle_category)"></td><td class="tbl-num" x-text="o.available"></td><td class="tbl-num" x-text="o.occupied"></td><td class="tbl-num" x-text="o.reserved"></td><td class="tbl-num" x-text="o.unavailable"></td>
                        <td><form class="flex items-center gap-2" @submit.prevent="setCapacity(o.vehicle_category)"><label class="sr-only" :for="`cap-${o.vehicle_category}`">Capacity</label>
                            <input :id="`cap-${o.vehicle_category}`" type="number" min="0" max="5000" class="input w-24" x-model="cap[o.vehicle_category]"><button class="btn-secondary btn-sm" :disabled="busy || Number(cap[o.vehicle_category]) === o.total">Set</button></form></td></tr></template>
                </tbody></table></div><p class="px-4 py-3 text-xs text-ink-muted">Capacity cannot go below occupied plus reserved slots. Removed slots are decommissioned, not deleted.</p></section>
        </div>
        <section class="card mt-4">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3"><h2 class="card-title">Slots</h2>
                <div class="flex items-center gap-2"><label class="text-sm" for="sl-f">Show</label><select id="sl-f" class="input w-44" x-model="filter"><option value="">All</option><option value="available">Available</option><option value="occupied">Occupied</option><option value="reserved">Reserved</option><option value="unavailable">Out of Service</option></select></div></div>
            <div class="grid grid-cols-3 gap-2 p-4 sm:grid-cols-5 md:grid-cols-7 xl:grid-cols-10">
                <template x-for="s in shownSlots" :key="s.id">
                    <button type="button" class="rounded-lg border px-2 py-2 text-left text-xs hover:shadow-[var(--shadow-card)]" @click="openSlot(s)"
                        :class="{ 'bg-success border-success-line text-success-ink': s.status==='available', 'bg-info border-info-line text-info-ink': s.status==='occupied', 'bg-warning border-warning-line text-warning-ink': s.status==='reserved', 'bg-neutral border-neutral-line text-neutral-ink': s.status==='unavailable' }"
                        :aria-label="`Slot ${s.code}: ${$fmt.stateLabel('slot', s.status)}`">
                        <span class="block font-mono font-bold" x-text="s.code"></span><span x-text="$fmt.stateLabel('slot', s.status)"></span></button>
                </template>
            </div>
            <p x-show="!shownSlots.length" class="px-4 pb-4 text-sm text-ink-muted">No slots to show.</p>
        </section>
        <x-modal show="slot" close="slot = null" title=":`Slot ${slot?.code}`">
            <form id="sl-fm" class="space-y-3" @submit.prevent="saveSlot()">
                <div><label class="label" for="sl-s">Status</label><select id="sl-s" class="input" x-model="slotForm.status"><option value="available">Available</option><option value="reserved">Reserved</option><option value="unavailable">Out of Service</option></select></div>
                <div><label class="label" for="sl-n">Notes</label><input id="sl-n" class="input" x-model="slotForm.notes" maxlength="255" placeholder="e.g. drain repair"></div></form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="slot = null">Cancel</button><button form="sl-fm" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
        </x-modal>
        <x-modal show="edit" title="Edit zone">
            <form id="ze-f" class="grid gap-3" @submit.prevent="save()">
                <div><label class="label" for="ze-n">Name</label><input id="ze-n" class="input" x-model="f.name" required maxlength="100"><p class="field-error" x-show="errors.name" x-text="errors.name?.[0]"></p></div>
                <fieldset><legend class="label">Vehicle categories allowed</legend><div class="flex flex-wrap gap-3"><template x-for="c in ref?.vehicle_categories || []" :key="c.id"><label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" :value="c.id" x-model.number="f.vehicle_category_ids"><span x-text="c.name"></span></label></template></div>
                    <p class="field-error" x-show="errors.vehicle_category_ids" x-text="errors.vehicle_category_ids?.[0]"></p></fieldset>
                <div><label class="label" for="ze-d">Description</label><textarea id="ze-d" class="input" x-model="f.description" maxlength="1000"></textarea></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="f.is_active"> Active (inactive zones accept no new Check-Ins)</label></form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="edit = false">Cancel</button><button form="ze-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
        </x-modal>
    </div></template>
</div>
</x-layouts.app>
