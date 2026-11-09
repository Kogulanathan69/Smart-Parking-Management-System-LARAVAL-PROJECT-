<x-layouts.app title="Reservation" :crumbs="['My reservations' => route('my.reservations')]">
<div x-data="{ r: null, loading: true, error: null, notFound: false, async reload() { try { this.r = (await ParkFlow.api.get('/customer/reservations/{{ (int) $id }}')).data; } catch (e) { this.error = e.message; this.notFound = e.status === 404 || e.status === 403; } finally { this.loading = false } }, init() { this.reload() } }">
    @include('pages.ops._record-states', ['var' => 'r'])
    <template x-if="r"><div class="grid gap-4 lg:grid-cols-3">
        <section class="card card-pad"><div class="flex items-center justify-between"><span class="plate text-lg" x-text="r.vehicle?.identifier"></span><x-state kind="reservation" value="r.status"/></div>
            <dl class="dl mt-4"><dt>Package</dt><dd x-text="r.package?.name"></dd><dt>Zone / slot</dt><dd x-text="`${r.zone?.code} / ${r.slot?.code}`"></dd><dt>From</dt><dd x-text="$fmt.dateTime(r.starts_at)"></dd><dt>Until</dt><dd x-text="$fmt.dateTime(r.expires_at)"></dd>
                <dt>Remaining</dt><dd x-text="r.remaining_minutes ? $fmt.minutes(r.remaining_minutes) : '—'"></dd></dl>
            <p class="mt-3 text-xs text-ink-muted">To renew or cancel, speak to the parking office.</p></section>
        <section class="card lg:col-span-2"><h2 class="card-title px-4 pt-4">Periods</h2><div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>#</th><th>From</th><th>To</th><th class="tbl-num">Price</th><th>Paid</th></tr></thead><tbody>
            <template x-for="p in r.periods || []" :key="p.sequence"><tr><td x-text="p.sequence"></td><td x-text="$fmt.dateTime(p.starts_at)"></td><td x-text="$fmt.dateTime(p.ends_at)"></td><td class="tbl-num" x-text="$fmt.money(p.price_minor)"></td><td><span :class="p.paid ? 'chip-success' : 'chip-warning'" x-text="p.paid ? 'Paid' : 'Due at exit'"></span></td></tr></template></tbody></table></div></section>
    </div></template>
</div>
</x-layouts.app>
