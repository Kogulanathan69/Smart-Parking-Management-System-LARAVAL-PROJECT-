<x-layouts.app title="Reservation" :crumbs="['Reservations' => route('ops.reservations')]">
<div x-data="{ r: null, visits: [], loading: true, error: null, notFound: false, busy: false, cancel: { open: false, reason: '', refund: '' },
    async reload() { this.loading = true; try { const x = await ParkFlow.api.get('/staff/reservations/{{ $id }}'); this.r = x.data; this.visits = x.visits || x.data.visits || []; this.error = null; } catch (e) { this.error = e.message; this.notFound = e.status === 404; } finally { this.loading = false; } },
    init() { this.reload(); },
    async renew() { if (!(await ParkFlow.confirm({ title: 'Renew this reservation?', body: 'A new period is added and billed at the published package price.', confirmLabel: 'Renew' }))) return;
        this.busy = true; try { await ParkFlow.api.post('/staff/reservations/{{ $id }}/renew'); ParkFlow.toast('Reservation renewed.'); await this.reload(); } catch (e) { ParkFlow.toast(e.message, 'danger'); } finally { this.busy = false; } },
    async doCancel() { this.busy = true; try { const b = { reason: this.cancel.reason }; const m = ParkFlow.fmt.rupeesToMinor(this.cancel.refund); if (m) b.refund_minor = m;
        await ParkFlow.api.post('/admin/reservations/{{ $id }}/cancel', b); ParkFlow.toast('Reservation cancelled.'); this.cancel.open = false; await this.reload(); } catch (e) { ParkFlow.toast(e.message, 'danger'); } finally { this.busy = false; } } }">
    @include('pages.ops._record-states', ['var' => 'r'])
    <template x-if="r"><div>
        <x-page-header title="Reservation">
            <button type="button" class="btn-primary" x-show="r.status === 'active'" @click="renew()" :disabled="busy">Renew</button>
            <button type="button" class="btn-danger" x-show="r.status === 'active' && $store.app.can('admin','super_admin')" @click="cancel.open = true">Cancel reservation</button>
        </x-page-header>
        <div class="grid gap-4 lg:grid-cols-3">
            <section class="detail-hero"><div class="flex items-center justify-between"><span class="plate text-lg" x-text="r.vehicle?.identifier"></span><x-state kind="reservation" value="r.status"/></div>
                <dl class="dl mt-4"><dt>Zone / slot</dt><dd x-text="`${r.zone?.code} / ${r.slot?.code}`"></dd><dt>Package</dt><dd x-text="r.package?.name"></dd>
                    <dt>Starts</dt><dd x-text="$fmt.dateTime(r.starts_at)"></dd><dt>Expires</dt><dd x-text="$fmt.dateTime(r.expires_at)"></dd>
                    <dt>Remaining</dt><dd x-text="r.remaining_minutes ? $fmt.minutes(r.remaining_minutes) : '—'"></dd><dt>Vehicle on site</dt><dd x-text="r.vehicle_on_site ? 'Yes' : 'No'"></dd>
                    <dt x-show="r.notes">Notes</dt><dd x-show="r.notes" x-text="r.notes"></dd></dl>
                <a class="btn-secondary btn-sm mt-3" x-show="r.current_session_uuid" :href="`/ops/sessions/${r.current_session_uuid}`">Open current session</a></section>
            <section class="card lg:col-span-2"><h2 class="card-title px-4 pt-4">Billing periods</h2>
                <div class="table-wrap mt-2"><table class="tbl"><thead><tr><th>#</th><th>Kind</th><th>From</th><th>To</th><th class="tbl-num">Price</th><th>Paid</th></tr></thead><tbody>
                    <template x-for="p in r.periods || []" :key="p.sequence"><tr><td x-text="p.sequence"></td><td x-text="$fmt.titleCase(p.kind)"></td><td x-text="$fmt.dateTime(p.starts_at)"></td><td x-text="$fmt.dateTime(p.ends_at)"></td><td class="tbl-num" x-text="$fmt.money(p.price_minor)"></td><td><span :class="p.paid ? 'chip-success' : 'chip-warning'" x-text="p.paid ? 'Paid' : 'Not yet paid'"></span></td></tr></template>
                </tbody></table></div></section>
        </div>
        <x-modal show="cancel.open" title="Cancel reservation">
            <form id="rc-f" class="space-y-3" @submit.prevent="doCancel()">
                <div><label class="label" for="rc-r">Reason</label><textarea id="rc-r" class="input" x-model="cancel.reason" minlength="3" maxlength="500" required></textarea></div>
                <div><label class="label" for="rc-m">Refund (LKR, optional)</label><input id="rc-m" class="input" inputmode="decimal" x-model="cancel.refund"><p class="hint">Creates a refund request for approval in Refunds.</p></div>
            </form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="cancel.open = false">Keep reservation</button><button form="rc-f" class="btn-danger" :disabled="busy">Cancel reservation</button></x-slot:footer>
        </x-modal>
    </div></template>
</div>
</x-layouts.app>
