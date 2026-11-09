<x-layouts.app title="Vehicle register" wide>
<div x-data="{ ref: null }" x-init="ref = await $store.app.reference()">
<div x-data="dataTable({ url: '/staff/vehicles', filters: { q: '', vehicle_category_id: '', fuel_type: '' } })">
    <x-page-header title="Vehicle register" subtitle="Vehicles are registered automatically at Check-In or by customers in their account."/>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-4" @submit.prevent="search()">
        <div class="filter-heading"><span class="filter-heading-icon"><x-icon name="search"/></span><div><h2 class="card-title">Find a vehicle</h2><p class="text-xs text-ink-muted">Search the operational vehicle register.</p></div></div>
        <div class="sm:col-span-2"><label class="label" for="v-q">Plate</label><input id="v-q" class="input" x-model="filters.q" maxlength="30"></div>
        <div><label class="label" for="v-c">Category</label><select id="v-c" class="input" x-model="filters.vehicle_category_id"><option value="">All</option><template x-for="c in ref?.vehicle_categories || []" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select></div>
        <div class="flex items-end gap-2"><button class="btn-primary flex-1">Search</button><button type="button" class="btn-secondary" @click="reset()">Reset</button></div>
    </form>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Plate</th><th>Category</th><th>Fuel</th><th>Make / model</th><th>Owner</th><th>Status</th><th>Registered</th></tr></thead>
        <tbody><x-list-states cols="7" empty="No vehicles found."/>
            <template x-for="v in rows" :key="v.id"><tr>
                <td><a :href="`/ops/vehicles/${v.id}`" class="hover:no-underline"><span class="plate text-xs" x-text="v.plate_number || v.identifier"></span></a><span x-show="v.is_reference" class="chip-neutral ml-1">No plate</span></td>
                <td x-text="$fmt.titleCase(v.vehicle_category)"></td><td x-text="v.fuel_type_label"></td><td x-text="[v.make, v.model, v.color].filter(Boolean).join(' · ') || '—'"></td>
                <td x-text="v.owner_id ? 'Customer account' : 'Walk-in'"></td><td><span :class="v.has_active_session ? 'chip-brand' : 'chip-neutral'" x-text="v.has_active_session ? 'Parked' : 'Not parked'"></span></td>
                <td x-text="$fmt.date(v.created_at)"></td></tr></template>
        </tbody></table></div><x-pager/></div>
</div></div>
</x-layouts.app>
