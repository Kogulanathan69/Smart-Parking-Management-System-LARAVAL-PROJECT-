<x-layouts.app title="Availability" wide>
<div x-data="{ ref: null }" x-init="ref = await $store.app.reference()"><div x-data="dataTable({ url: '/staff/availability', filters: { vehicle_category_id: '' } })">
    <x-page-header title="Availability" subtitle="Live slot counts per zone and vehicle type, from the server.">
        <div class="flex items-end gap-2"><div><label class="label" for="av-c">Vehicle type</label><select id="av-c" class="input" x-model="filters.vehicle_category_id" @change="search()"><option value="">All types</option>
            <template x-for="c in ref?.vehicle_categories || []" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select></div>
            <button type="button" class="btn-secondary" @click="load()"><x-icon name="refresh" class="h-4 w-4"/>Refresh</button></div>
    </x-page-header>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        <template x-for="z in rows" :key="z.zone_id">
            <section class="card card-pad" :aria-label="`Zone ${z.code}`">
                <div class="flex items-start justify-between gap-2"><div><h2 class="card-title" x-text="`Zone ${z.code}`"></h2><p class="text-sm text-ink-muted" x-text="z.name"></p></div>
                    <span :class="z.is_active ? (z.available > 0 ? 'chip-success' : 'chip-danger') : 'chip-neutral'" x-text="!z.is_active ? 'Inactive' : (z.available > 0 ? `${z.available} free` : 'Full')"></span></div>
                <div class="table-wrap mt-3"><table class="tbl"><thead><tr><th>Type</th><th class="tbl-num">Free</th><th class="tbl-num">Occupied</th><th class="tbl-num">Reserved</th><th class="tbl-num">Out of service</th></tr></thead><tbody>
                    <template x-for="c in z.by_vehicle_category" :key="c.vehicle_category"><tr><td x-text="$fmt.titleCase(c.vehicle_category)"></td><td class="tbl-num font-semibold" x-text="c.available"></td><td class="tbl-num" x-text="c.occupied"></td><td class="tbl-num" x-text="c.reserved"></td><td class="tbl-num" x-text="c.unavailable"></td></tr></template>
                </tbody></table></div>
                <p class="mt-3 text-xs text-ink-muted" x-show="z.packages?.length" x-text="'Packages: ' + (z.packages || []).map(p => p.name).join(', ')"></p>
            </section>
        </template>
    </div>
    <p x-show="empty" class="card card-pad text-ink-muted">No zones configured.</p>
</div></div>
</x-layouts.app>
