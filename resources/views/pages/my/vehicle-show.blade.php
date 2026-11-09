<x-layouts.app title="Vehicle details" :crumbs="['My vehicles' => route('my.vehicles')]" wide>
<div x-data="{
    v: null, active: null, loading: true, error: null, notFound: false,
    async loadVehicle() {
        this.loading = true; this.error = null
        try {
            const [vehicle, sessions] = await Promise.all([
                ParkFlow.api.get('/customer/vehicles/{{ (int) $id }}'),
                ParkFlow.api.get('/customer/sessions', { vehicle_id: {{ (int) $id }}, status: 'active,exit_authorized', per_page: 1 })
            ])
            this.v = vehicle.data
            this.active = sessions.data?.[0] || null
        } catch (e) {
            this.error = e.message; this.notFound = [403, 404].includes(e.status)
        } finally { this.loading = false }
    },
    init() { this.loadVehicle() }
}">
    <x-page-header title="Vehicle details" subtitle="Registration details and parking activity for this vehicle."><a href="{{ route('my.vehicles') }}" class="btn-secondary">← Back to my vehicles</a></x-page-header>

    <template x-if="loading"><div class="card card-pad" role="status"><div class="skeleton h-7 w-1/3"></div><div class="skeleton mt-4 h-28"></div><span class="sr-only">Loading vehicle details…</span></div></template>
    <template x-if="error"><div class="alert-danger flex flex-wrap items-center justify-between gap-2" role="alert"><span x-text="notFound ? 'Vehicle not found or you do not have access to it.' : error"></span><button type="button" class="btn-secondary btn-sm" @click="loadVehicle()">Try again</button></div></template>

    <template x-if="v"><div class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-5">
            <section class="card card-pad bg-gradient-to-br from-surface to-brand-faint/60 lg:col-span-3" aria-labelledby="vehicle-summary">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Registration number</p><h2 id="vehicle-summary" class="plate mt-1 text-2xl" x-text="v.plate_number || v.identifier"></h2></div><span :class="active ? 'chip-brand' : 'chip-neutral'" x-text="active ? 'Currently parked' : 'Not currently parked'"></span></div>
                <dl class="dl mt-5 sm:grid-cols-[9rem_1fr_9rem_1fr]">
                    <dt>Vehicle category</dt><dd x-text="$fmt.titleCase(v.vehicle_category)"></dd><dt>Fuel type</dt><dd x-text="v.fuel_type_label || $fmt.titleCase(v.fuel_type)"></dd>
                    <dt>Make</dt><dd x-text="v.make || '—'"></dd><dt>Model</dt><dd x-text="v.model || '—'"></dd>
                    <dt>Colour</dt><dd x-text="v.color || '—'"></dd><dt>Registered</dt><dd x-text="$fmt.dateTime(v.created_at)"></dd>
                </dl>
            </section>

            <section class="card card-pad border-brand-soft lg:col-span-2" aria-labelledby="parking-summary">
                <h2 id="parking-summary" class="card-title">Current parking</h2>
                <template x-if="!active"><div><p class="mt-3 text-sm text-ink-muted">Not currently parked.</p><a href="{{ route('my.active') }}" class="btn-secondary btn-sm mt-4">Open active parking</a></div></template>
                <template x-if="active"><div><dl class="dl mt-3"><dt>Reference</dt><dd x-text="active.reference"></dd><dt>Checked in</dt><dd x-text="$fmt.dateTime(active.entry_at)"></dd><dt>Zone / slot</dt><dd x-text="`${active.zone?.code ?? '—'} / ${active.slot?.code ?? '—'}`"></dd><dt>Package</dt><dd x-text="active.package?.name || '—'"></dd></dl><div class="mt-4 flex flex-wrap gap-2"><a :href="`/my/history/${active.uuid}`" class="btn-primary btn-sm">View active session</a><a href="{{ route('my.active') }}" class="btn-secondary btn-sm">All active parking</a></div></div></template>
            </section>
        </div>

        <section id="history" aria-labelledby="vehicle-history" x-data="dataTable({ url: '/customer/sessions', param: { vehicle_id: {{ (int) $id }} }, perPage: 10, syncUrl: false })">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2"><div><h2 id="vehicle-history" class="card-title">Parking history</h2><p class="mt-1 text-sm text-ink-muted">Sessions recorded for this vehicle under your account.</p></div><a href="{{ route('my.history') }}" class="btn-secondary btn-sm">All parking history</a></div>
            @include('pages.ops._session-table', ['link' => '/my/history/'])
        </section>
    </div></template>
</div>
</x-layouts.app>
