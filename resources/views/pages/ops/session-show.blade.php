<x-layouts.app title="Parking session" :crumbs="['Parked vehicles' => route('ops.sessions')]">
<div x-data="sessionDetail(@js($uuid))">
    @include('pages.ops._record-states', ['var' => 's'])
    <template x-if="s">
    <div>
        <x-page-header title="Session" subtitle="">
            <a :href="`/ops/check-out`" class="btn-primary no-print" x-show="s.status === 'active'"><x-icon name="arrow-out" class="h-4 w-4"/>Check-Out</a>
            <button type="button" class="btn-secondary no-print" @click="printTicket()" x-show="s.status === 'active'"><x-icon name="printer" class="h-4 w-4"/>Print ticket</button>
            <button type="button" class="btn-secondary no-print" @click="reload()"><x-icon name="refresh" class="h-4 w-4"/>Refresh</button>
        </x-page-header>
        <div class="no-print grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <section class="detail-hero">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="text-sm text-ink-muted">Session reference</p><p class="text-2xl font-bold tracking-tight" x-text="s.reference"></p></div>
                        <div class="flex flex-wrap items-center gap-2"><span class="plate text-lg" x-text="s.vehicle?.plate_number || s.vehicle?.identifier"></span><x-state kind="session" value="s.status"/></div>
                    </div>
                    <dl class="dl mt-4 sm:grid-cols-[9rem_1fr_9rem_1fr]">
                        <dt>Category</dt><dd x-text="`${$fmt.titleCase(s.vehicle_category)} · ${$fmt.titleCase(s.fuel_type)}`"></dd>
                        <dt>Zone / slot</dt><dd x-text="`${s.zone?.code} / ${s.slot?.code}`"></dd>
                        <dt>Package</dt><dd x-text="s.package?.name"></dd>
                        <dt>Safety add-on</dt><dd x-text="s.has_safety ? 'Yes' : 'No'"></dd>
                        <dt>Checked in</dt><dd x-text="$fmt.dateTime(s.entry_at)"></dd>
                        <dt>Package ends</dt><dd x-text="s.package_ends_at ? $fmt.dateTime(s.package_ends_at) : 'No fixed end'"></dd>
                        <dt>Time</dt><dd><x-state kind="time" value="s.time?.status"/></dd>
                        <dt>Checked out</dt><dd x-text="$fmt.dateTime(s.exit_at)"></dd>
                        <dt>Customer</dt><dd x-text="s.customer_name || (s.customer_id ? `Account #${s.customer_id}` : 'Walk-in')"></dd>
                        <dt>Receipt</dt><dd x-text="s.receipt_number || '—'"></dd>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a class="btn-secondary btn-sm" :href="`/ops/vehicles/${s.vehicle?.id}`" x-show="s.vehicle?.id">Vehicle history</a>
                        <a class="btn-secondary btn-sm" :href="`/ops/incidents/new?session=${s.uuid}`">Report incident</a>
                        <button type="button" class="btn-secondary btn-sm" @click="confirmLeft()" x-show="s.status === 'exit_authorized'" :disabled="busy">Confirm vehicle has left</button>
                        <button type="button" class="btn-secondary btn-sm" @click="reissue()" x-show="s.status === 'active'">Reissue lost ticket</button>
                    </div>
                </section>

                <section class="card card-pad"><h2 class="card-title">Bill <span class="text-sm font-normal text-ink-muted" x-text="isOpen ? '(live, as of now)' : '(final)'"></span></h2>
                    <div class="mt-3" x-data="{ get b() { return bill } }">@include('pages.partials._bill')</div></section>

                <section class="card card-pad">
                    <div class="flex items-center justify-between"><h2 class="card-title">Payments</h2>
                        <button type="button" class="btn-secondary btn-sm" x-show="s.status === 'active'" @click="openPrepay()">Take prepayment</button></div>
                    <div class="table-wrap mt-3"><table class="tbl"><thead><tr><th>Reference</th><th>Method</th><th>Status</th><th class="tbl-num">Amount</th><th>When</th><th></th></tr></thead><tbody>
                        <template x-for="p in payments" :key="p.id"><tr>
                            <td class="font-mono text-xs" x-text="p.reference"></td><td x-text="$fmt.titleCase(p.method)"></td><td><x-state kind="payment" value="p.status"/></td>
                            <td class="tbl-num" x-text="$fmt.money(p.amount_minor)"></td><td x-text="$fmt.dateTime(p.paid_at || p.created_at)"></td>
                            <td class="text-right"><template x-if="p.status === 'pending'"><span class="flex justify-end gap-1"><button class="btn-secondary btn-sm" @click="resolvePayment(p, true)">Received</button><button class="btn-ghost btn-sm" @click="resolvePayment(p, false)">Failed</button></span></template></td></tr></template>
                        <tr x-show="!payments.length"><td colspan="6" class="py-4 text-center text-ink-muted">No payments yet.</td></tr>
                    </tbody></table></div>
                </section>
            </div>

            <div class="space-y-4">
                <section class="card card-pad">
                    <div class="flex items-center justify-between"><h2 class="card-title">Penalties</h2>
                        <button type="button" class="btn-secondary btn-sm" x-show="s.status === 'active'" @click="open.penalty = true">Apply</button></div>
                    <ul class="mt-3 divide-y divide-line text-sm">
                        <template x-for="p in penalties" :key="p.id"><li class="py-2">
                            <div class="flex items-center justify-between gap-2"><span class="font-semibold" x-text="p.type?.name"></span><span class="tabular-nums" x-text="$fmt.money(p.amount_minor)"></span></div>
                            <div class="mt-1 flex items-center justify-between gap-2"><x-state kind="penalty" value="p.status"/>
                                <button type="button" class="btn-ghost btn-sm" x-show="p.status === 'applied' && s.status === 'active' && $store.app.can('admin','super_admin')" @click="waive(p)">Waive</button></div>
                            <p class="text-xs text-ink-muted" x-text="p.reason"></p></li></template>
                        <li x-show="!penalties.length" class="py-2 text-ink-muted">None.</li>
                    </ul>
                </section>
                <section class="card card-pad">
                    <div class="flex items-center justify-between"><h2 class="card-title">Safety inspections</h2>
                        <button type="button" class="btn-secondary btn-sm" x-show="isOpen" @click="open.inspection = true">Record</button></div>
                    <ul class="mt-3 divide-y divide-line text-sm">
                        <template x-for="i in inspections" :key="i.id"><li class="py-2"><div class="flex justify-between gap-2"><span x-text="$fmt.titleCase(i.type)"></span><x-state kind="inspection" value="i.status"/></div>
                            <p class="text-xs text-ink-muted" x-text="i.performed_at ? `${$fmt.dateTime(i.performed_at)} · ${$fmt.stateLabel('condition', i.condition)}${i.new_damage_found ? ' · new damage' : ''}` : `Due ${$fmt.dateTime(i.scheduled_for)}`"></p></li></template>
                        <li x-show="!inspections.length" class="py-2 text-ink-muted">None recorded.</li>
                    </ul>
                </section>
                <section class="card card-pad"><h2 class="card-title">Gate history</h2>
                    <ul class="mt-3 divide-y divide-line text-sm">
                        <template x-for="(g, i) in gate" :key="i"><li class="py-2"><div class="flex items-center justify-between gap-2"><span x-text="$fmt.titleCase(g.direction)"></span><x-state kind="gate" value="g.status === 'success' && g.driver === 'log' ? 'simulated' : g.status"/></div>
                            <p class="text-xs text-ink-muted" x-text="`${$fmt.dateTime(g.created_at)} · driver ${g.driver}${g.driver === 'log' ? ' (simulated)' : ''}`"></p></li></template>
                        <li x-show="!gate.length" class="py-2 text-ink-muted">No gate commands.</li>
                    </ul>
                    <button type="button" class="btn-secondary btn-sm mt-2" x-show="['completed','exit_authorized'].includes(s.status) && gate.length && !gate.some(g => g.status === 'success')" @click="retryGate()" :disabled="busy">Retry gate</button>
                </section>
            </div>
        </div>

        {{-- print-only ticket --}}
        <div class="print-only text-center" x-show="ticket"><h2 class="text-xl font-bold">ParkFlow</h2><div class="mx-auto my-3 w-56" x-html="ticket?.qr_svg"></div>
            <p class="font-semibold" x-text="ticket?.session_reference"></p><p x-text="`${ticket?.vehicle} - Zone ${ticket?.zone} - ${ticket?.slot}`"></p><p x-text="`In: ${ticket?.entry_at_local}`"></p>
            <p class="mt-2 text-sm font-semibold" x-text="ticket?.instruction"></p></div>

        <x-modal show="open.penalty" title="Apply penalty">
            <form id="pen-f" class="space-y-3" @submit.prevent="applyPenalty()">
                <div><label class="label" for="pen-t">Penalty</label><select id="pen-t" class="input" x-model="penalty.penalty_type_id" required><option value="">Choose…</option>
                    <template x-for="t in types" :key="t.id"><option :value="t.id" x-text="`${t.name} — ${$fmt.money(t.amount_minor)}`"></option></template></select></div>
                <div><label class="label" for="pen-r">Reason</label><textarea id="pen-r" class="input" x-model="penalty.reason" minlength="3" maxlength="500" required></textarea></div>
                <p class="text-xs text-ink-muted">Penalties are separate from overtime and are written to the audit trail.</p>
            </form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="open.penalty = false">Cancel</button><button form="pen-f" class="btn-primary" :disabled="busy">Apply penalty</button></x-slot:footer>
        </x-modal>
        <x-modal show="open.prepay" title="Take prepayment">
            <form id="pp-f" class="space-y-3" @submit.prevent="doPrepay()">
                <div><label class="label" for="pp-m">Method</label><select id="pp-m" class="input" x-model="prepay.method"><template x-for="m in (ref?.payment_methods || [])" :key="m.code"><option :value="m.code" x-text="m.name"></option></template></select></div>
                <div><label class="label" for="pp-a">Amount (LKR)</label><input id="pp-a" class="input" inputmode="decimal" x-model="prepay.amount" required></div>
                <div x-show="prepay.method === 'cash'"><label class="label" for="pp-t">Cash received (LKR)</label><input id="pp-t" class="input" inputmode="decimal" x-model="prepay.tendered"></div>
                <div><label class="label" for="pp-r">Reference</label><input id="pp-r" class="input" x-model="prepay.external_reference" maxlength="100"></div>
                <p class="text-xs text-ink-muted">Any unused prepaid amount becomes customer credit or a refund at Check-Out, per the server's rules.</p>
            </form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="open.prepay = false">Cancel</button><button form="pp-f" class="btn-primary" :disabled="busy">Record payment</button></x-slot:footer>
        </x-modal>
        <x-modal show="open.inspection" title="Record safety inspection">
            <form id="in-f" class="space-y-3" @submit.prevent="recordInspection()">
                <div><label class="label" for="in-t">Type</label><select id="in-t" class="input" x-model="inspection.type"><option value="entry">Entry</option><option value="routine">Routine</option><option value="exit">Exit</option></select></div>
                <div><label class="label" for="in-c">Condition</label><select id="in-c" class="input" x-model="inspection.condition"><option value="good">Good</option><option value="minor_damage">Minor damage</option><option value="major_damage">Major damage</option></select></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" x-model="inspection.new_damage_found"> New damage found</label>
                <div><label class="label" for="in-n">Notes</label><textarea id="in-n" class="input" x-model="inspection.notes" maxlength="2000"></textarea></div>
            </form>
            <x-slot:footer><button type="button" class="btn-secondary" @click="open.inspection = false">Cancel</button><button form="in-f" class="btn-primary" :disabled="busy">Save</button></x-slot:footer>
        </x-modal>
    </div>
    </template>
</div>
</x-layouts.app>
