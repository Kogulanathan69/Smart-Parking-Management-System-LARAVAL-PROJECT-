<x-layouts.app title="Credit balance">
<div x-data="{ d: null, error: null, async init() { try { this.d = await ParkFlow.api.get('/customer/credits') } catch (e) { this.error = e.message } } }">
    <x-page-header title="Credit balance" subtitle="Credit from overpayments or unused prepaid time. Staff apply it at Check-Out."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="d"><div class="space-y-4">
        <div class="kpi max-w-xs"><span class="kpi-label">Available credit</span><span class="kpi-value" x-text="$fmt.money(d.balance_minor)"></span></div>
        <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Description</th><th class="tbl-num">Amount</th><th>Expires</th></tr></thead><tbody>
            <template x-for="(e, i) in d.entries" :key="i"><tr><td x-text="$fmt.dateTime(e.created_at)"></td><td x-text="e.description || $fmt.titleCase(e.reason || e.kind || e.type || 'Credit')"></td><td class="tbl-num" :class="e.amount_minor < 0 ? 'text-danger-ink' : ''" x-text="$fmt.money(e.amount_minor)"></td><td x-text="$fmt.date(e.expires_at, '—')"></td></tr></template>
            <tr x-show="!d.entries.length"><td colspan="4" class="py-6 text-center text-ink-muted">No credit history.</td></tr></tbody></table></div></div>
    </div></template>
</div>
</x-layouts.app>
