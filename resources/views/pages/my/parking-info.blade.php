<x-layouts.app title="Parking info & prices" wide>
<div x-data="{
    d: null, error: null, cat: '', zoneCode: '', packageCode: '',
    async init() { try { this.d = (await ParkFlow.api.get('/customer/parking-info')).data } catch (e) { this.error = e.message } },
    vehicleChanged() { this.zoneCode = ''; this.packageCode = '' },
    zoneChanged() { this.packageCode = '' },
    get priceRows() { return (this.d?.packages || []).flatMap(p => p.prices.filter(r => !this.cat || r.vehicle_category === this.cat).map(r => ({ ...r, pkg: p, key: p.code + r.vehicle_category }))) },
    get zonesForVehicle() { return !this.cat ? [] : (this.d?.zones || []).filter(z => (z.by_vehicle_category || []).some(c => c.vehicle_category === this.cat && Number(c.available) > 0)) },
    get selectedZone() { return (this.d?.zones || []).find(z => z.code === this.zoneCode) || null },
    availableFor(z) { return (z?.by_vehicle_category || []).find(c => c.vehicle_category === this.cat)?.available ?? 0 },
    get packagesForSelection() {
        if (!this.cat || !this.selectedZone) return []
        return (this.d?.packages || []).filter(p => {
            const zoneTypes = p.allowed_zone_types || []
            return (zoneTypes.length === 0 || zoneTypes.includes(this.selectedZone.zone_type)) && (p.prices || []).some(r => r.vehicle_category === this.cat)
        })
    },
    get selectedPackage() { return this.packagesForSelection.find(p => p.code === this.packageCode) || null },
    get selectedRate() { return this.selectedPackage?.prices?.find(r => r.vehicle_category === this.cat) || null },
    get selectedVehicle() { return (this.d?.vehicle_categories || []).find(c => c.code === this.cat) || null },
    packageTerms(p) {
        if (!p) return '—'
        if (p.duration_minutes) return $fmt.minutes(p.duration_minutes)
        return ({ hourly: 'Hourly billing', night: 'Night billing', fixed: 'Fixed price' })[p.billing_mode] || p.billing_mode
    }
}">
    <x-page-header title="Parking info & prices" subtitle="Free spaces right now and the published prices. You cannot book a slot online; staff assign it at the gate."/>

    <template x-if="!d && !error"><div class="card card-pad" role="status"><div class="skeleton h-6 w-1/3"></div><div class="skeleton mt-3 h-24"></div><span class="sr-only">Loading parking information…</span></div></template>
    <template x-if="error"><div class="alert-danger" role="alert"><p x-text="error"></p><button type="button" class="btn-secondary btn-sm mt-2" @click="error = null; init()">Try again</button></div></template>

    <template x-if="d"><div class="space-y-4">
        <section class="card card-pad" aria-labelledby="preview-h">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div><h2 id="preview-h" class="card-title">Preview a published price</h2><p class="mt-1 text-sm text-ink-muted">Choose in order to see the base price for an available, compatible option.</p></div>
                <p class="text-xs text-ink-muted" x-text="`Availability as of ${$fmt.dateTime(d.as_of)}`"></p>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div><label class="label" for="pi-c">1. Vehicle type</label>
                    <select id="pi-c" class="input" x-model="cat" @change="vehicleChanged()"><option value="">Choose a vehicle type…</option><template x-for="c in d.vehicle_categories" :key="c.code"><option :value="c.code" x-text="c.name"></option></template></select>
                    <p class="hint">This also filters the published prices below.</p></div>
                <div><label class="label" for="pi-z">2. Parking zone</label>
                    <select id="pi-z" class="input" x-model="zoneCode" @change="zoneChanged()" :disabled="!cat"><option value="" x-text="cat ? 'Choose an available zone…' : 'Choose a vehicle type first…'"></option><template x-for="z in zonesForVehicle" :key="z.code"><option :value="z.code" x-text="`Zone ${z.code} — ${z.name} (${availableFor(z)} free)`"></option></template></select>
                    <p class="hint" x-show="cat && !zonesForVehicle.length">No zones currently have space for this vehicle type.</p><p class="hint" x-show="cat && zonesForVehicle.length">Only zones with free compatible spaces are shown.</p></div>
                <div><label class="label" for="pi-p">3. Parking package</label>
                    <select id="pi-p" class="input" x-model="packageCode" :disabled="!selectedZone"><option value="" x-text="selectedZone ? 'Choose a compatible package…' : 'Choose a zone first…'"></option><template x-for="p in packagesForSelection" :key="p.code"><option :value="p.code" x-text="p.name"></option></template></select>
                    <p class="hint" x-show="selectedZone && !packagesForSelection.length">No published package rate is available for this selection.</p><p class="hint" x-show="selectedZone && packagesForSelection.length">Packages follow the zone compatibility and current rate card.</p></div>
            </div>

            <div class="mt-5 rounded-lg border border-line bg-neutral p-4" aria-live="polite">
                <template x-if="!selectedRate"><p class="text-sm text-ink-muted">Complete all three selections to preview the published base price.</p></template>
                <template x-if="selectedRate"><div>
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Estimated published base price</p><p class="mt-1 text-3xl font-semibold tabular-nums" x-text="$fmt.money(selectedRate.price_minor)"></p></div><span class="chip-info" x-text="d.currency"></span></div>
                    <dl class="dl mt-4 sm:grid-cols-[9rem_1fr_9rem_1fr]">
                        <dt>Vehicle type</dt><dd x-text="selectedVehicle?.name"></dd><dt>Zone</dt><dd x-text="selectedZone ? `Zone ${selectedZone.code} — ${selectedZone.name}` : '—'"></dd>
                        <dt>Package</dt><dd x-text="selectedPackage?.name"></dd><dt>Duration / billing</dt><dd x-text="packageTerms(selectedPackage)"></dd>
                        <dt>Overtime / hour</dt><dd x-text="$fmt.money(selectedRate.overtime_hourly_minor)"></dd><dt>Grace period</dt><dd x-text="`${selectedRate.grace_minutes ?? 0} min`"></dd>
                    </dl>
                    <p class="mt-3 text-sm text-ink-muted" x-show="selectedPackage?.description" x-text="selectedPackage?.description"></p>
                    <p class="mt-4 text-xs font-medium text-ink-muted">Estimated published base price only. Final charges are calculated at Check-Out according to the applicable billing rules.</p>
                </div></template>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Free spaces by zone">
            <template x-for="z in d.zones" :key="z.code"><div class="card card-pad"><p class="font-semibold" x-text="`Zone ${z.code}`"></p><p class="text-xs text-ink-muted" x-text="z.name"></p><p class="mt-2 text-2xl font-semibold tabular-nums" x-text="cat ? (z.by_vehicle_category.find(c => c.vehicle_category === cat)?.available ?? 'Not for this type') : z.available"></p><p class="text-xs text-ink-muted">free spaces</p></div></template>
        </section>

        <section class="card"><h2 class="card-title px-4 pt-4">Published prices (LKR)</h2>
            <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>Package</th><th>Vehicle</th><th class="tbl-num">Price</th><th class="tbl-num">Overtime / hour</th><th class="tbl-num">Grace</th></tr></thead><tbody>
                <template x-for="r in priceRows" :key="r.key"><tr><td><span class="font-semibold" x-text="r.pkg.name"></span><span class="block text-xs text-ink-muted" x-show="r.pkg.guarantees_slot">Includes a dedicated slot for the whole period</span></td><td x-text="d.vehicle_categories.find(c => c.code === r.vehicle_category)?.name"></td><td class="tbl-num" x-text="$fmt.money(r.price_minor)"></td><td class="tbl-num" x-text="$fmt.money(r.overtime_hourly_minor)"></td><td class="tbl-num" x-text="`${r.grace_minutes ?? 0} min`"></td></tr></template>
                <tr x-show="!priceRows.length"><td colspan="5" class="text-ink-muted">No published prices for this vehicle type.</td></tr>
            </tbody></table></div><p class="px-4 py-3 text-xs text-ink-muted" x-text="d.note"></p></section>
    </div></template>
</div>
</x-layouts.app>
