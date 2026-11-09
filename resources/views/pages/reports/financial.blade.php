<x-layouts.app title="Financial report" wide>
<div x-data="dataTable({ url: '/reports/financial', filters: { from: ParkFlow.fmt.todayLocal(-6), to: ParkFlow.fmt.todayLocal() }, transform: (rows, p) => [p.data] })">
    <x-page-header title="Financial report" subtitle="Settled payments counted by payment time (Sri Lanka days). Pending and failed payments are not revenue.">@include('pages.reports._range')</x-page-header>
    <template x-if="error"><div class="alert-danger" x-text="error"></div></template>
    <template x-for="fin in rows" :key="fin.from"><div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <x-kpi label="Revenue" value="$fmt.money(fin.revenue_minor)"/><x-kpi label="Payments" value="fin.payment_count"/><x-kpi label="Refunds" value="$fmt.money(fin.refunds_minor)" hint=":`${fin.refunds_pending} pending`"/>
            <x-kpi label="Pending payments" value="fin.pending_payments"/><x-kpi label="Failed payments" value="fin.failed_payments"/>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card"><div class="flex items-center justify-between px-4 pt-4"><h2 class="card-title">By day</h2><button type="button" class="btn-ghost btn-sm no-print" @click="$fmt.downloadCsv(`revenue-${fin.from}-${fin.to}.csv`, ['Date','Payments','Amount (LKR)'], fin.by_day.map(d => [d.date, d.count, (d.amount_minor/100).toFixed(2)]))"><x-icon name="download" class="h-4 w-4"/>CSV</button></div>
                <table class="tbl mt-2"><thead><tr><th>Date</th><th class="tbl-num">Payments</th><th class="tbl-num">Amount</th></tr></thead><tbody>
                    <template x-for="d in fin.by_day" :key="d.date"><tr><td x-text="$fmt.date(d.date)"></td><td class="tbl-num" x-text="d.count"></td><td class="tbl-num" x-text="$fmt.money(d.amount_minor)"></td></tr></template>
                    <tr x-show="!fin.by_day.length"><td colspan="3" class="text-ink-muted">No payments.</td></tr></tbody></table></section>
            <section class="card"><h2 class="card-title px-4 pt-4">By method</h2><table class="tbl mt-2"><thead><tr><th>Method</th><th class="tbl-num">Payments</th><th class="tbl-num">Amount</th></tr></thead><tbody>
                <template x-for="m in fin.by_method" :key="m.method"><tr><td x-text="$fmt.titleCase(m.method)"></td><td class="tbl-num" x-text="m.count"></td><td class="tbl-num" x-text="$fmt.money(m.amount_minor)"></td></tr></template>
                <tr x-show="!fin.by_method.length"><td colspan="3" class="text-ink-muted">No payments.</td></tr></tbody></table></section>
            <section class="card lg:col-span-2"><h2 class="card-title px-4 pt-4">Penalties</h2><table class="tbl mt-2"><thead><tr><th>Type</th><th class="tbl-num">Applied</th><th class="tbl-num">Amount</th></tr></thead><tbody>
                <template x-for="t in fin.penalties?.by_type || []" :key="t.code ?? t.type"><tr><td x-text="t.name ?? $fmt.titleCase(t.code ?? t.type)"></td><td class="tbl-num" x-text="t.count ?? t.applied_count"></td><td class="tbl-num" x-text="$fmt.money(t.amount_minor ?? t.applied_minor)"></td></tr></template>
                <tr><td class="font-semibold">Total applied</td><td class="tbl-num" x-text="fin.penalties?.applied_count"></td><td class="tbl-num font-semibold" x-text="$fmt.money(fin.penalties?.applied_minor)"></td></tr>
                <tr><td class="text-ink-muted">Waived</td><td class="tbl-num" x-text="fin.penalties?.waived_count"></td><td class="tbl-num" x-text="$fmt.money(fin.penalties?.waived_minor)"></td></tr></tbody></table></section>
        </div>
    </div></template>
</div>
</x-layouts.app>
