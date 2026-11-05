<x-layouts.app title="Parking info & prices" wide>
<div x-data="{ d: null, error: null, cat: '', async init() { try { this.d = (await ParkFlow.api.get('/customer/parking-info')).data } catch (e) { this.error = e.message } },
    get priceRows() { return (this.d?.packages || []).flatMap(p => p.prices.filter(r => !this.cat || r.vehicle_category === this.cat).map(r => ({ ...r, pkg: p, key: p.code + r.vehicle_category }))) } }">
    <x-page-header title="Parking info & prices" subtitle="Free spaces right now and the published prices. You cannot book a slot online; staff assign it at the gate."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="d"><div class="space-y-4">
        <div class="flex flex-wrap items-end gap-2"><div><label class="label" for="pi-c">My vehicle type</label><select id="pi-c" class="input" x-model="cat"><option value="">All types</option><template x-for="c in d.vehicle_categories" :key="c.code"><option :value="c.code" x-text="c.name"></option></template></select></div>
            <p class="text-xs text-ink-muted" x-text="`As of ${$fmt.dateTime(d.as_of)}`"></p></div>
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Free spaces by zone">
            <template x-for="z in d.zones" :key="z.code"><div class="card card-pad"><p class="font-semibold" x-text="`Zone ${z.code}`"></p><p class="text-xs text-ink-muted" x-text="z.name"></p>
                <p class="mt-2 text-2xl font-semibold tabular-nums" x-text="cat ? (z.by_vehicle_category.find(c => c.vehicle_category === cat)?.available ?? 'Not for this type') : z.available"></p><p class="text-xs text-ink-muted">free spaces</p></div></template>
        </section>
        <section class="card"><h2 class="card-title px-4 pt-4">Published prices (LKR)</h2>
            <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>Package</th><th>Vehicle</th><th class="tbl-num">Price</th><th class="tbl-num">Overtime / hour</th><th class="tbl-num">Grace</th></tr></thead><tbody>
                <template x-for="r in priceRows" :key="r.key">
                    <tr><td><span class="font-semibold" x-text="r.pkg.name"></span><span class="block text-xs text-ink-muted" x-show="r.pkg.guarantees_slot">Includes a dedicated slot for the whole period</span></td><td x-text="d.vehicle_categories.find(c => c.code === r.vehicle_category)?.name"></td>
                        <td class="tbl-num" x-text="$fmt.money(r.price_minor)"></td><td class="tbl-num" x-text="$fmt.money(r.overtime_hourly_minor)"></td><td class="tbl-num" x-text="`${r.grace_minutes ?? 0} min`"></td></tr></template>
                <tr x-show="!priceRows.length"><td colspan="5" class="text-ink-muted">No published prices for this vehicle type.</td></tr>
            </tbody></table></div>
            <p class="px-4 py-3 text-xs text-ink-muted" x-text="d.note"></p></section>
    </div></template>
</div>
</x-layouts.app>
