<x-layouts.app title="Reservations" wide>
<div x-data="dataTable({ url: '/staff/reservations', filters: { status: 'active', plate: '', expiring_within: '' } })">
    <x-page-header title="Guaranteed parking (reservations)" subtitle="Weekly and monthly packages hold a dedicated slot. Reservations are created at the vehicle's first Check-In on a guaranteed package."/>
    <form class="card mb-4 grid gap-3 p-4 sm:grid-cols-4" @submit.prevent="search()">
        <div><label class="label" for="r-p">Plate</label><input id="r-p" class="input" x-model="filters.plate" maxlength="30"></div>
        <div><label class="label" for="r-s">Status</label><select id="r-s" class="input" x-model="filters.status"><option value="">All</option><option value="active">Active</option><option value="expired">Expired</option><option value="cancelled">Cancelled</option></select></div>
        <div><label class="label" for="r-e">Expiring within</label><select id="r-e" class="input" x-model="filters.expiring_within"><option value="">Any time</option><option value="1440">1 day</option><option value="4320">3 days</option><option value="10080">7 days</option></select></div>
        <div class="flex items-end gap-2"><button class="btn-primary flex-1">Filter</button><button type="button" class="btn-secondary" @click="reset()">Reset</button></div>
    </form>
    <div class="card"><div class="table-wrap"><table class="tbl">
        <thead><tr><th>Vehicle</th><th>Status</th><th>Zone / slot</th><th>Package</th><th>Expires</th><th>On site</th></tr></thead>
        <tbody><x-list-states cols="6" empty="No reservations match."/>
            <template x-for="r in rows" :key="r.id"><tr>
                <td><a :href="`/ops/reservations/${r.id}`" class="hover:no-underline"><span class="plate text-xs" x-text="r.vehicle?.identifier"></span></a></td>
                <td><x-state kind="reservation" value="r.status"/></td><td x-text="`${r.zone?.code} / ${r.slot?.code}`"></td><td x-text="r.package?.name"></td>
                <td><span x-text="$fmt.dateTime(r.expires_at)"></span><span class="block text-xs text-ink-muted" x-show="r.remaining_minutes" x-text="`${$fmt.minutes(r.remaining_minutes)} left`"></span></td>
                <td x-text="r.vehicle_on_site === null ? '—' : (r.vehicle_on_site ? 'Yes' : 'No')"></td></tr></template>
        </tbody></table></div><x-pager/></div>
</div>
</x-layouts.app>
