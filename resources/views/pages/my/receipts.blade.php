<x-layouts.app title="Receipts">
<div x-data="dataTable({ url: '/customer/receipts', syncUrl: false })">
    <x-page-header title="Receipts" subtitle="Receipts for completed parking sessions."/>
    <div class="card"><div class="flex items-center gap-3 border-b border-line p-4"><span class="grid h-10 w-10 place-items-center rounded-lg bg-brand-faint text-brand-strong"><x-icon name="receipt" class="h-5 w-5"/></span><div><h2 class="card-title">Payment receipts</h2><p class="text-xs text-ink-muted">Open any receipt to print or save it as PDF.</p></div></div><div class="table-wrap"><table class="tbl"><thead><tr><th>Receipt</th><th>Session</th><th>Vehicle</th><th>Issued</th><th class="tbl-num">Paid</th></tr></thead><tbody>
        <x-list-states cols="5" empty="No receipts yet."/>
        <template x-for="r in rows" :key="r.number"><tr><td><a :href="`/my/receipts/${r.number}`" class="font-mono" x-text="r.number"></a></td><td x-text="r.session_reference || '—'"></td><td x-text="r.vehicle || '—'"></td><td x-text="$fmt.dateTime(r.issued_at)"></td><td class="tbl-num" x-text="$fmt.money(r.paid_minor)"></td></tr></template>
    </tbody></table></div><x-pager/></div>
</div>
</x-layouts.app>
