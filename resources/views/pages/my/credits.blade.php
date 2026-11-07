<x-layouts.app title="Credit balance">
<div x-data="{ d: null, error: null, async init() { try { this.d = await ParkFlow.api.get('/customer/credits') } catch (e) { this.error = e.message } } }">
    <x-page-header title="Credit balance" subtitle="Credit from overpayments or unused prepaid time. Staff apply it at Check-Out."/>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-if="d"><div class="space-y-4">
        <div class="card max-w-md overflow-hidden"><div class="bg-gradient-to-r from-navy to-brand-strong p-5 text-white"><div class="flex items-center justify-between"><span class="text-sm font-medium text-white/75">Available credit</span><x-icon name="card" class="h-6 w-6 text-white/80"/></div><span class="mt-3 block text-3xl font-bold tabular-nums" x-text="$fmt.money(d.balance_minor)"></span><p class="mt-1 text-xs text-white/70">Applied by staff during Check-Out</p></div></div>
        <div class="card"><div class="table-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Description</th><th class="tbl-num">Amount</th><th>Expires</th></tr></thead><tbody>
            <template x-for="(e, i) in d.entries" :key="i"><tr><td x-text="$fmt.dateTime(e.created_at)"></td><td x-text="e.description || $fmt.titleCase(e.reason || e.kind || e.type || 'Credit')"></td><td class="tbl-num" :class="e.amount_minor < 0 ? 'text-danger-ink' : ''" x-text="$fmt.money(e.amount_minor)"></td><td x-text="$fmt.date(e.expires_at, '—')"></td></tr></template>
            <tr x-show="!d.entries.length"><td colspan="4" class="py-6 text-center text-ink-muted">No credit history.</td></tr></tbody></table></div></div>
    </div></template>
</div>
</x-layouts.app>
