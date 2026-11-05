<x-layouts.app title="Occupancy report" wide>
<div x-data="{ occ: [], updated: null, error: null, async init() { await this.load(); setInterval(() => this.load(), 60000) }, async load() { try { this.occ = (await ParkFlow.api.get('/reports/occupancy')).data; this.updated = new Date().toISOString(); } catch (e) { this.error = e.message } }, pct(z) { return z.total ? Math.round((z.occupied + z.reserved) / z.total * 100) : 0 } }">
    <x-page-header title="Occupancy" subtitle="Live slot states by zone and vehicle type."><button type="button" class="btn-secondary no-print" @click="window.print()"><x-icon name="printer" class="h-4 w-4"/>Print</button></x-page-header>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    @include('pages.dashboards._occupancy')
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <template x-for="z in occ" :key="z.zone_id"><section class="card"><h2 class="card-title px-4 pt-4" x-text="`Zone ${z.code} by vehicle type`"></h2>
            <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>Type</th><th class="tbl-num">Available</th><th class="tbl-num">Occupied</th><th class="tbl-num">Reserved</th><th class="tbl-num">Out of service</th><th class="tbl-num">Total</th></tr></thead><tbody>
                <template x-for="c in z.by_vehicle_category" :key="c.vehicle_category"><tr><td x-text="$fmt.titleCase(c.vehicle_category)"></td><td class="tbl-num" x-text="c.available"></td><td class="tbl-num" x-text="c.occupied"></td><td class="tbl-num" x-text="c.reserved"></td><td class="tbl-num" x-text="c.unavailable"></td><td class="tbl-num" x-text="c.total"></td></tr></template></tbody></table></div></section></template>
    </div>
</div>
</x-layouts.app>
